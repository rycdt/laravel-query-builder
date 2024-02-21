<?php

namespace Rycdt;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

trait QueryBuilder
{
    public $operators = [
        'EQ' => '=',
        'NEQ' => '!=',
        'GT' => '>',
        'GTE' => '>=',
        'LT' => '<',
        'LTE' => '<=',
        'ILIKE' => 'ILIKE',
        'LIKE' => 'LIKE',
        'NOT_LIKE' => 'NOT LIKE'
    ];

    public $inConstraints = [
        'IN' => 'whereIn',
        'NOT_IN' => 'whereNotIn'
    ];

    public $nullConstraints = [
        'NULL' => 'whereNull',
        'NOT_NULL' => 'whereNotNull'
    ];

    /**
     * Query builder
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @para array params
     * @return mixed
     */
    private function queryBuilder($builder, $params)
    {
        return $builder
            ->when($selects = $params['selects'] ?? null, function ($query) use ($selects) {
                return $this->handleSelect($query, $selects);
            })
            ->when($concats = $params['concats'] ?? null, function ($query) use ($concats) {
                return $this->handleConcat($query, $concats);
            })
            ->when($where = $params['where'] ?? null, function ($query) use ($where) {
                return $this->handleWhere($query, $where);
            })
            ->when($sort = $params['sort'] ?? null, function ($query) use ($sort) {
                return $this->handleSort($query, $sort);
            })
            ->when($has = $params['has'] ?? null, function ($query) use ($has) {
                return $this->handleHasRelation($query, $has);
            })
            ->when($aggregates = $params['aggregates'] ?? null, function ($query) use ($aggregates) {
                return $this->handleAggregate($query, $aggregates);
            })
            ->when($includes = $params['includes'] ?? null, function ($query) use ($includes) {
                return $this->handleInclude($query, $includes);
            })
            ->when(isset($params['first']) && isset($params['page']), function ($query) use ($params) {
                // Paginate only when both first and page are provided
                return $query->paginate(
                    $params['first'] ?? null,
                    ['*'],
                    'page',
                    $params['page'] ?? null
                );
            }, function ($query) {
                // Do not paginate when either first or page is missing
                return $query->get();
            });
    }

    /**
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param mixed $whereConstraints
     * @param bool $nestedOr
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    private function handleWhere($builder, $whereConstraints, bool $nestedOr = false)
    {
        if ($andConnectedConstraints = $whereConstraints['AND'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($andConnectedConstraints): void {
                    foreach ($andConnectedConstraints as $constraint) {
                        $this->handleWhere($builder, $constraint);
                    }
                }
            );
        }

        if ($orConnectedConstraints = $whereConstraints['OR'] ?? null) {
            $builder->whereNested(
                function ($builder) use ($orConnectedConstraints): void {
                    foreach ($orConnectedConstraints as $constraint) {
                        $this->handleWhere($builder, $constraint, true);
                    }
                }
            );
        }

        if ($column = $whereConstraints['column'] ?? null) {
            if (!array_key_exists('operator', $whereConstraints)) {
                return $builder;
            }
            if (!array_key_exists('value', $whereConstraints) &&
                !array_key_exists($whereConstraints['operator'], $this->nullConstraints)) {
                return $builder;
            }

            if ($where = $this->inConstraints[$whereConstraints['operator']] ?? null) {
                $where = $nestedOr
                    ? 'or' . ucfirst($where)
                    : $where;
                $builder->{$where}(
                    $column,
                    $whereConstraints['value']
                );
            } elseif ($where = $this->nullConstraints[$whereConstraints['operator']] ?? null) {
                $where = $nestedOr
                    ? 'or' . ucfirst($where)
                    : $where;
                $builder->{$where}($column);
            } elseif ($this->operators[$whereConstraints['operator']] ?? null) {
                $where = $nestedOr
                    ? 'orWhere'
                    : 'where';
                $builder->{$where}(
                    $column,
                    $this->operators[$whereConstraints['operator']],
                    $whereConstraints['value']
                );
            }
        }

        return $builder;
    }

    /**
     * Apply an "ORDER BY" clause.
     *
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param mixed $value
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    private function handleSort($builder, $value)
    {
        foreach ($value as $orderByClause) {
            $builder->orderBy(
                DB::raw($orderByClause['field']),
                $orderByClause['order']
            );
        }

        return $builder;
    }

    /**
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param $whereConstraints
     * @param bool $nestedOr
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    private function handleHasRelation($builder, $whereConstraints, bool $nestedOr = false)
    {
        if ($andConnectedConstraints = $whereConstraints['AND'] ?? null) {
            $builder->where(
                function ($builder) use ($andConnectedConstraints): void {
                    foreach ($andConnectedConstraints as $constraint) {
                        $this->handleHasRelation($builder, $constraint);
                    }
                }
            );
        }

        if ($orConnectedConstraints = $whereConstraints['OR'] ?? null) {
            $builder->where(
                function ($builder) use ($orConnectedConstraints): void {
                    foreach ($orConnectedConstraints as $constraint) {
                        $this->handleHasRelation($builder, $constraint, true);
                    }
                }
            );
        }

        if ($relation = $whereConstraints['relation'] ?? null) {
            $where = $whereConstraints['where'] ?? [];

            $has = $nestedOr
                ? 'orWhereHas'
                : 'whereHas';

            $builder->{$has}($relation, function ($subBuilder) use ($where) {
                return $this->handleWhere($subBuilder, $where);
            });
        }

        return $builder;
    }

    /**
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param $value
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    private function handleAggregate($builder, $value)
    {
        foreach ($value as $aggregate) {
            $relation = $aggregate['relation'] ?? null;
            $type = $aggregate['type'] ?? null;
            $field = $aggregate['field'] ?? null;
            $where = $aggregate['where'] ?? [];
            if ($relation && $type) {
                $fieldAggregate = $type !== 'count';
                if ($fieldAggregate) {
                    if (!$field) {
                        continue;
                    }
                    $relation .= " as $field" . "_$type";
                }

                $builder->withCount([
                    $relation => function ($subBuilder) use ($where, $field, $type, $fieldAggregate) {
                        if ($fieldAggregate) {
                            $subBuilder = $subBuilder->select(DB::raw("$type($field) as $field" . "_$type"));
                        }
                        return $where ? $this->handleWhere($subBuilder, $where) : $subBuilder;
                    }
                ]);
            }
        }

        return $builder;
    }

    /**
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param $value
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    private function handleInclude($builder, $value)
    {
        foreach ($value as $include) {
            if ($relation = $include['relation'] ?? null) {
                $builder->with([$relation => function ($subBuilder) use ($include) {
                    return $this->queryBuilder($subBuilder, $include);
                }]);
            }
        }

        return $builder;
    }

    /**
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param $selects
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleSelect($builder, $selects)
    {
        return $builder
            ->when($selects, function ($subBuilder) use ($selects) {
                return $subBuilder->select($selects);
            });
    }

    /**
     * @param Builder|\Illuminate\Database\Eloquent\Builder $builder
     * @param $concats
     * @return Builder|\Illuminate\Database\Eloquent\Builder
     */
    public function handleConcat($builder, $concats)
    {
        $concatenated = [];
        foreach ($concats as $concat) {
            $convention = $concat['convention'] ?? null;
            $alias = $concat['alias'] ?? null;
            if ($convention && $alias) {
                $concatenated[] =
                    'CONCAT(' . implode(', ', $convention) . ') as ' . $alias;
            }
        }

        return $builder
            ->when($concats, function ($subBuilder) use ($concatenated) {
                return $subBuilder->selectRaw(implode(', ', $concatenated));
            });
    }


    /**
     * @param $args
     * @return array
     */
    public function handleDistinct($args)
    {
        $model = $args['model'] ?? null;
        $columns = $args['columns'] ?? null;
        $first = $args['first'] ?? null;
        $page = $args['page'] ?? null;
        $where = $args['where'] ?? null;
        $sort = $args['sort'] ?? null;
        $className = "App\\Models\\$model";

        if (class_exists($className)) {
            /** @var Model $model */
            $model = app($className);

            if ($result = $this->validateColumns($model, $columns)) {
                return $result;
            }

            /** @var Builder $query */
            $query = $model::query();

            if ($where) {
                $query = $this->handleWhere($query, $where);
            }

            if ($sort) {
                $query = $this->handleSort($query, $sort);
            }

            /** @var Builder $data */
            $data = $query->distinct();
            if ($first && $page) {
                $data = $data->paginate($first, $columns, null, $page);
            } else {
                if ($first) {
                    $data = $data->limit($first);
                }
                $data = $data->get($columns);
            }

            return [
                'success' => true,
                'message' => 'Successfully retrieved distinct columns',
                'data' => $data
            ];
        }

        return [
            'success' => false,
            'message' => 'Model does not exist'
        ];
    }

    /**
     * @param Model $model
     * @param string|array $columns
     * @return array|null
     */
    private function validateColumns($model, $columns)
    {
        $table_name = $model->getTable();
        $invalid = '';
        if (is_array($columns)) {
            foreach ($columns as $column) {
                if (!$model->getConnection()->getSchemaBuilder()->hasColumn($table_name, $column)) {
                    $invalid = $column;
                    break;
                }
            }
        } else {
            $invalid = !$model->getConnection()->getSchemaBuilder()->hasColumn($table_name, $columns) ? $columns : '';
        }

        return $invalid ? [
            'success' => false,
            'message' => 'Invalid column \'' . $invalid . '\'',
        ] : null;
    }
}
