<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexLookupRequest;
use App\Models\Department;
use App\Models\SupplyRequest;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    private const LIMIT = 20;

    /**
     * Saran departemen peminjam: nama dari master departemen (maks. 20).
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
