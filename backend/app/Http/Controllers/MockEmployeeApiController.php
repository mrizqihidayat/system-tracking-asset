<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * API pegawai TIRUAN di dalam aplikasi, hanya untuk tahap uji coba di Vercel (T38), saat API pegawai asli
 * (hanya bisa diakses dari jaringan INALUM) belum terjangkau. Data fiktif dari database/mock/employees.json.
 *
 * Mati secara bawaan (EMPLOYEE_MOCK_ENABLED=false → 404) dan memakai API key yang sama dengan klien
 * (EMPLOYEE_API_KEY, header EMPLOYEE_API_KEY_HEADER), seperti API asli. Hapus saat produksi.
 */
class MockEmployeeApiController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $key = (string) config('employees.api_key');

        if (! config('employees.mock_enabled') || $key === '') {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        $given = (string) $request->header((string) config('employees.api_key_header'));

        if (! hash_equals($key, $given)) {
            return response()->json(['message' => 'API key tidak valid.'], 401);
        }

        $employees = json_decode((string) file_get_contents(base_path('database/mock/employees.json')), true);
        $dataKey = (string) config('employees.data_key');

        return response()->json($dataKey !== '' ? [$dataKey => $employees] : $employees);
    }
}
