<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang passwordnya baru direset staf SHD wajib mengganti password, dan akun yang belum punya email
 * wajib mengisinya dulu (T40, SPEC §15): semua endpoint ditolak 403 kecuali `/user`, `/user/email`,
 * `/user/password`, dan `/logout`. Akun `email_exempt` (staf1) dikecualikan dari kewajiban email.
 */
class EnsureAccountReady
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Ganti password dulu sebelum memakai aplikasi.',
                'code' => 'password_change_required',
            ], 403);
        }

        if ($request->user()?->needsEmail()) {
            return response()->json([
                'message' => 'Isi email dulu sebelum memakai aplikasi.',
                'code' => 'email_required',
            ], 403);
        }

        return $next($request);
    }
}
