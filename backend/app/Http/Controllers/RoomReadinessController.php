<?php

namespace App\Http\Controllers;

use App\Services\RoomReadinessService;
use Illuminate\Http\JsonResponse;

/**
 * Ringkasan kesiapan ruangan per gedung: "Kurang N item" / "Lengkap" + jumlah Belum dicek terpisah.
 */
class RoomReadinessController extends Controller
{
    public function __invoke(RoomReadinessService $readiness): JsonResponse
    {
        return response()->json(['data' => $readiness->summary()]);
    }
}
