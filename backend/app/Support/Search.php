<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Database\Query\Builder;

/**
 * Pencarian "mengandung" tanpa membedakan huruf besar/kecil (PostgreSQL & SQLite), dipakai
 * controller daftar dan scope filter model (daftar & laporan harus memakai aturan yang sama).
 */
class Search
{
    /**
     * Filter pada satu atau beberapa kolom (OR). $orWhere(query, pattern) untuk kondisi OR tambahan,
     * mis. whereHas relasi.
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  string|list<string>  $columns
     * @return TBuilder
     */
    public static function apply(Builder $query, mixed $search, string|array $columns = 'name', ?Closure $orWhere = null): Builder
    {
        $search = is_string($search) ? trim($search) : '';

        if ($search === '') {
            return $query;
        }

        $pattern = '%'.addcslashes(mb_strtolower($search), '%_\\').'%';

        return $query->where(function ($query) use ($columns, $pattern, $orWhere) {
            foreach ((array) $columns as $column) {
                $query->orWhereRaw(self::likeSql($column), [$pattern]);
            }

            if ($orWhere !== null) {
                $orWhere($query, $pattern);
            }
        });
    }

    public static function likeSql(string $column): string
    {
        return "lower({$column}) like ? escape '\\'";
    }
}
