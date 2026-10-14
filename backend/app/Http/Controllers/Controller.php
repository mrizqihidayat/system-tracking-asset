<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\Database\Query\Builder;

abstract class Controller
{
    /**
     * Filter "mengandung" tanpa membedakan huruf besar/kecil (PostgreSQL & SQLite).
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    protected function applySearch(Builder $query, mixed $search, string $column = 'name'): Builder
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return $query;
        }

        $pattern = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

        return $query->whereRaw("lower({$column}) like ? escape '\\'", [$pattern]);
    }
}
