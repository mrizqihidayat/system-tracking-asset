<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmployeePushRequest;
use App\Services\EmployeeSyncService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Penerima kiriman agen sinkron pegawai (T44, folder `employee-sync-agent/`). API pegawai hanya bisa dijangkau dari
 * jaringan perusahaan, jadi agen di server perusahaan yang mengambil datanya lalu mengirim ke sini lewat HTTPS.
 * Aturan sinkron sama dengan `employees:sync`. Kiriman yang diproses selalu 200 dengan hasil di `status`
 * (berhasil | gagal | dibatalkan), supaya agen tidak mengulang kiriman yang sudah tercatat; 503 bila sinkronisasi
 * lain sedang berjalan.
 */
class EmployeePushController extends Controller
{
    public function __invoke(EmployeePushRequest $request, EmployeeSyncService $sync): JsonResponse
    {
        try {
            $run = $request->has('error')
                ? $sync->recordAgentFailure((string) $request->input('error'))
                : $sync->import($request->input('employees'));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 503);
        }

        return response()->json($run->only(['status', 'total', 'created', 'updated', 'deactivated', 'skipped', 'message']));
    }
}
