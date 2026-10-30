<?php

namespace App\Http\Controllers\Concerns;

use App\Support\AppCache;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Daftar data master (combobox) dari cache grup master; pencarian tidak di-cache.
 * Bentuk respons tetap { data: [...] }.
 */
trait CachesMasterList
{
    /**
     * @param  Closure(): array<int, mixed>  $load  daftar yang sudah di-resolve (array, bukan model)
     */
    protected function masterList(Request $request, string $key, Closure $load): JsonResponse
    {
        $data = filled($request->query('search'))
            ? $load()
            : AppCache::remember(AppCache::GROUP_MASTER, $key, $load);

        return response()->json(['data' => $data]);
    }
}
