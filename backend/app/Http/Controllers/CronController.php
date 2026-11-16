<?php

namespace App\Http\Controllers;

use App\Models\EmployeeSyncRun;
use App\Services\EmployeeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Endpoint untuk Vercel Cron (T38). Vercel tidak punya `schedule:run`, jadi sinkronisasi pegawai harian
 * dipanggil lewat HTTP GET dengan `Authorization: Bearer {CRON_SECRET}`. Logikanya sama dengan `employees:sync`.
 */
class CronController extends Controller
{
    public function employeesSync(Request $request, EmployeeSyncService $sync): JsonResponse
    {
        $secret = (string) config('employees.cron_secret');
        $given = (string) $request->bearerToken();

        // Tanpa CRON_SECRET endpoint dianggap tidak ada (bukan terbuka).
        if ($secret === '') {
            return response()->json(['message' => 'Data tidak ditemukan.'], 404);
        }

        if (! hash_equals($secret, $given)) {
            return response()->json(['message' => 'Tidak diizinkan.'], 401);
        }

        try {
            $run = $sync->run();
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        $ok = $run->status === EmployeeSyncRun::SUCCESS;

        return response()->json([
            'status' => $run->status,
            'total' => $run->total,
            'created' => $run->created,
            'updated' => $run->updated,
            'deactivated' => $run->deactivated,
            'skipped' => $run->skipped,
            'message' => $run->message,
        ], $ok ? 200 : 502);
    }
}
