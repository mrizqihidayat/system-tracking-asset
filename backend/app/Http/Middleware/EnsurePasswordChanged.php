<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Akun yang wajib ganti password hanya boleh memanggil GET /user, PUT /user/password, dan POST /logout (SPEC §15).
 */
class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->user()?->must_change_password) {
            return response()->json([
                'message' => 'Ganti password terlebih dahulu.',
                'code' => 'password_change_required',
            ], 403);
        }

        return $next($request);
    }
}
