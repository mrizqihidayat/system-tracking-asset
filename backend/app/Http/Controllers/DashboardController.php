<?php

namespace App\Http\Controllers;

use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    /**
     * Semua kartu & panel dashboard (SPEC §11) dalam satu respons.
     */
    public function __invoke(Request $request, DashboardService $dashboard): JsonResponse
    {
        return response()->json(['data' => $dashboard->data($request)]);
    }
}
