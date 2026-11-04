<?php

namespace App\Http\Controllers;

use App\Models\Consumable;
use App\Models\Department;
use App\Models\SupplyRequest;
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
     * Saran lokasi simpan stok: nilai yang pernah diinput, tanpa duplikat (maks. 20).
     */
    public function storageLocations(Request $request): JsonResponse
    {
        $locations = $this->applySearch(Consumable::query()->whereNotNull('storage_location'), $request->query('search'), 'storage_location')
            ->distinct()
            ->orderBy('storage_location')
            ->limit(self::LIMIT)
            ->pluck('storage_location');

        return response()->json(['data' => $locations]);
    }

    /**
     * Saran cara permintaan: default dulu, lalu nilai yang pernah dipakai di permintaan.
     * Tanpa duplikat (tidak membedakan huruf besar/kecil), maks. 20.
     */
    public function requestMethods(Request $request): JsonResponse
    {
        $search = mb_strtolower(trim((string) $request->query('search', '')));
        $methods = collect(SupplyRequest::DEFAULT_REQUEST_METHODS)
            ->filter(fn (string $method): bool => $search === '' || str_contains(mb_strtolower($method), $search))
            ->values();

        return response()->json(['data' => $methods]);
    }
}
