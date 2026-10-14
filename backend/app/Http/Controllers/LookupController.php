<?php

namespace App\Http\Controllers;

use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LookupController extends Controller
{
    /**
     * Saran unit peminjam: nama dari master departemen (maks. 20).
     */
    public function borrowerUnits(Request $request): JsonResponse
    {
        $units = $this->applySearch(Department::query(), $request->query('search'))
            ->orderBy('name')
            ->limit(20)
            ->pluck('name');

        return response()->json(['data' => $units]);
    }
}
