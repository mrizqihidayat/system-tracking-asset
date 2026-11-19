<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeResource;
use App\Models\Employee;
use App\Models\EmployeeSyncRun;
use App\Support\Search;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private const LIMIT = 10;

    /**
     * Saran pegawai aktif dari salinan lokal (semua departemen) untuk Nama peminjam & PIC: maks. 10,
     * hanya nama/departemen/jabatan. Hanya mencari NAMA (T45, temuan UAT K1): mencari NIK memungkinkan
     * orang mencocokkan NIK dengan nama, padahal NIK adalah password awal akun pegawai SHD.
     */
    public function index(Request $request): JsonResponse
    {
        $search = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']])['search'];

        $employees = Search::apply(Employee::query()->active(), $search, 'name')
            ->orderBy('name')
            ->limit(self::LIMIT)
            ->get();

        return response()->json(['data' => EmployeeResource::collection($employees)->resolve($request)]);
    }

    /**
     * Sinkron terakhir (hanya baca) untuk strip di Data Master > Departemen: status berhasil/gagal/dibatalkan.
     */
    public function syncStatus(): JsonResponse
    {
        $run = EmployeeSyncRun::query()->latest('started_at')->latest('id')->first();

        return response()->json(['data' => $run ? [
            'status' => $run->status,
            'started_at' => $run->started_at->toIso8601String(),
            'finished_at' => $run->finished_at?->toIso8601String(),
            'total' => $run->total,
            'created' => $run->created,
            'updated' => $run->updated,
            'deactivated' => $run->deactivated,
            'skipped' => $run->skipped,
            'message' => $run->message,
        ] : null]);
    }
}
