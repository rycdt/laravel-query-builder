<?php

namespace Rycdt;

use Illuminate\Support\ServiceProvider;

class QueryBuilderServiceProvider extends ServiceProvider
{
    public function boot()
    {
    }

    public function register()
    {
        $this->app->bind(QueryBuilder::class);
        $this->app->bind(ValidationBuilder::class);
    }
}