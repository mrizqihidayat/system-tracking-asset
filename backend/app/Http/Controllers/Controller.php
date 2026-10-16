<?php

namespace App\Http\Controllers;

use App\Support\Search;
use Closure;
use Illuminate\Contracts\Database\Query\Builder;

abstract class Controller
{
    /**
     * Filter "mengandung" tanpa membedakan huruf besar/kecil (lihat Search::apply()).
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  string|list<string>  $columns
     * @return TBuilder
     */
    protected function applySearch(Builder $query, mixed $search, string|array $columns = 'name', ?Closure $orWhere = null): Builder
    {
        return Search::apply($query, $search, $columns, $orWhere);
    }

    protected function likeSql(string $column): string
    {
        return Search::likeSql($column);
    }
}
