<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexLookupRequest;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\SupplyRequest;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    private const LIMIT = 20;

    /**
     * Saran unit peminjam: nama dari master departemen (maks. 20).
     */
    public function borrowerUnits(IndexLookupRequest $request): JsonResponse
    {
        $units = $this->applySearch(Department::query(), $request->validated('search'))
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->pluck('name');

        return response()->json(['data' => $units]);
    }

    /**
     * Saran lokasi simpan stok: nilai yang pernah diinput, tanpa duplikat (maks. 20).
     */
    public function storageLocations(IndexLookupRequest $request): JsonResponse
    {
        $locations = $this->applySearch(Consumable::query()->whereNotNull('storage_location'), $request->validated('search'), 'storage_location')
            ->distinct()
            ->orderBy('storage_location')
            ->limit(self::LIMIT)
            ->pluck('storage_location');

        return response()->json(['data' => $locations]);
    }

    /**
     * Pilihan cara permintaan: satu sumber untuk form web dan versi HP (nilai "Lainnya" = isian teks bebas).
     */
    public function requestMethods(IndexLookupRequest $request): JsonResponse
    {
        $search = mb_strtolower(trim((string) $request->validated('search')));
        $methods = collect(SupplyRequest::DEFAULT_REQUEST_METHODS)
            ->filter(fn (string $method): bool => $search === '' || str_contains(mb_strtolower($method), $search))
            ->values();

        return response()->json(['data' => $methods]);
    }
}
