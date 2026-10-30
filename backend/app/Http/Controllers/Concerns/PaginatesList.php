<?php

namespace App\Http\Controllers\Concerns;

use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Paginasi + sort server yang seragam untuk endpoint daftar (pasangan ValidatesListQuery).
 * Respons tetap bentuk paginator Laravel: data, links, meta.
 */
trait PaginatesList
{
    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @param  array<string, mixed>  $params  hasil validated()
     * @param  array<string, string>  $sortable  kunci sort => kolom tabel
     * @param  Closure(Builder<TModel>): mixed  $defaultOrder  urutan bila sort kosong
     * @return LengthAwarePaginator<int, TModel>
     */
    protected function paginateList(Builder $query, array $params, array $sortable, Closure $defaultOrder, int $perPage): LengthAwarePaginator
    {
        $sort = $params['sort'] ?? null;

        if ($sort) {
            $key = ltrim($sort, '-');
            $query->orderBy($sortable[$key], str_starts_with($sort, '-') ? 'desc' : 'asc')
                ->orderBy($query->getModel()->getQualifiedKeyName());
        } else {
            $defaultOrder($query);
        }

        return $query->paginate($params['per_page'] ?? $perPage)->withQueryString();
    }
}
