<?php

namespace Rycdt;

use Illuminate\Support\Facades\Log;

trait ValidationBuilder
{
    /**
     * Custom model validation messages
     * @param $args
     * @return array
     */
    public function validationMessages($args): array
    {
        $customValidations = $args['validationMessages'] ?? [];
        $validationMessages = [];
        foreach ($customValidations as $column => $validation) {
            $rule = $validation['rule'] ?? null;
            $message = $validation['message'] ?? null;
            if ($rule && $message) {
                $validationMessages[$column . '.' . $rule] = $message;
            }
        }
        return $validationMessages;
    }

    /**
     * Custom model rules
     * @param $args
     * @param $rules
     * @return array
     */
    public function validationRules($args, $rules): array
    {
        $customValidations = $args['validationRules'] ?? [];
        foreach ($customValidations as $column => $validation) {
            $modelRule = $rules[$column];
            $replace = $validation['replace'] ?? null;
            $customRule = $validation['with'] ?? null;
            if ($replace && $customRule) {
                $rules[$column] = str_replace($replace, $customRule, $modelRule);
            }
        }
        return $rules;
    }
}
