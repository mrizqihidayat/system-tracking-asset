<?php

namespace App\Http\Controllers;

use App\Http\Resources\EmployeeResource;
use App\Services\AuthService;
use App\Services\EmployeeDirectory\EmployeeDirectory;
use App\Services\EmployeeDirectory\EmployeeDirectoryUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    private const LIMIT = 10;

    /**
     * Saran pegawai (semua departemen) untuk Nama peminjam & PIC: maks. 10, hanya nik/nama/departemen/jabatan.
     */
    public function index(Request $request, EmployeeDirectory $directory): JsonResponse
    {
        $search = $request->validate(['search' => ['required', 'string', 'min:2', 'max:100']])['search'];

        try {
            $employees = $directory->search($search, self::LIMIT);
        } catch (EmployeeDirectoryUnavailable) {
            return response()->json(['message' => AuthService::MSG_DIRECTORY_DOWN], 503);
        }

        return response()->json(['data' => EmployeeResource::collection($employees)->resolve($request)]);
    }
}
