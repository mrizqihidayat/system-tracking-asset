<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kelola akun hanya untuk akun pegawai SHD (source api); akun bersama ditolak 403 (SPEC §15).
 * Wajib ganti password sudah ditolak lebih dulu oleh EnsurePasswordChanged.
 */
class EnsureEmployeeAccount
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->isApi()) {
            return response()->json([
                'message' => 'Hanya akun pegawai SHD yang bisa mengelola akun.',
                'code' => 'employee_account_required',
            ], 403);
        }

        return $next($request);
    }
}
