<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dashboard (SPEC §11) per kelompok widget: dimuat paralel oleh frontend, masing-masing di-cache.
 */
class DashboardController extends Controller
{
    public function __construct(private DashboardService $dashboard) {}

    /**
     * Semua kartu ringkasan.
     */
    public function cards(): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->cards()]);
    }

    /**
     * Panel kesiapan ruangan per gedung.
     */
    public function roomReadiness(): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->roomReadiness()]);
    }

    /**
     * Panel daftar: jatuh tempo, permintaan terbaru, perlu servis, stok menipis.
     */
    public function panels(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard->panels($request)]);
    }
}
