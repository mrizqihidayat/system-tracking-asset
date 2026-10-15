<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\RequestCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    private const LIMIT = 20;

    /**
     * Saran unit peminjam: nama dari master departemen (maks. 20).
     */
    public function borrowerUnits(Request $request): JsonResponse
    {
        $units = $this->applySearch(Department::query(), $request->query('search'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->pluck('name');

        return response()->json(['data' => $units]);
    }

    /**
     * Saran cara permintaan: default dulu, lalu nilai yang pernah dipakai di katalog.
     * Tanpa duplikat (tidak membedakan huruf besar/kecil), maks. 20.
     */
    public function requestMethods(Request $request): JsonResponse
    {
        $used = $this->applySearch(RequestCatalog::query(), $request->query('search'), 'request_method')
            ->distinct()
            ->orderBy('request_method')
            ->pluck('request_method');

        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $defaults = collect(RequestCatalog::DEFAULT_REQUEST_METHODS)
            ->filter(fn (string $method): bool => $search === '' || str_contains(mb_strtolower($method), $search));

        $methods = $defaults->concat($used)
            ->unique(fn (string $method): string => mb_strtolower(trim($method)))
            ->take(self::LIMIT)
            ->values();

        return response()->json(['data' => $methods]);
    }
}
