<?php

namespace App\Http\Controllers;

use App\Exceptions\LoginRejected;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Resources\AccountResource;
use App\Services\AuthService;
use App\Services\EmployeeDirectory\EmployeeDirectoryUnavailable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function __construct(private AuthService $auth) {}

    /**
     * Satu kolom "NIK / Username" (field `username`): angka saja = NIK pegawai SHD, selain itu akun manual (SPEC §15).
     */
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            // Regex dengan flag u menolak karakter NUL dan teks bukan UTF-8 sebelum sampai ke database.
            'username' => ['required', 'string', 'max:50', 'regex:/^[^\x00]*$/u'],
            'password' => 'required|string',
        ]);
        $loginId = trim($data['username']);

        try {
            $user = $this->auth->attempt($loginId, $data['password']);
        } catch (LoginRejected $e) {
            Log::channel('security')->info('login.failed', ['username' => $loginId, 'reason' => $e->reason, 'ip' => $request->ip()]);

            throw $e;
        }

        $token = $user->createToken('web')->plainTextToken;

        Log::channel('security')->info('login.success', ['user_id' => $user->id, 'username' => $user->loginId(), 'ip' => $request->ip()]);

        return response()->json(['token' => $token, 'user' => AccountResource::make($user)->resolve($request)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(AccountResource::make($request->user())->resolve($request));
    }

    /**
     * Ganti password sendiri. Akun api yang wajib ganti memakai tanggal lahir sebagai password saat ini.
     * Token lain milik akun ini dicabut; token yang sedang dipakai tetap berlaku.
     */
    public function changePassword(ChangePasswordRequest $request): JsonResponse
    {
        $user = $request->user();

        try {
            $currentValid = $this->auth->checkCurrentPassword($user, $request->validated('current_password'));
        } catch (EmployeeDirectoryUnavailable) {
            return response()->json(['message' => AuthService::MSG_DIRECTORY_DOWN], 503);
        }

        if (! $currentValid) {
            throw ValidationException::withMessages(['current_password' => 'Password saat ini salah.']);
        }

        $this->auth->setPassword($user, $request->validated('password'), $user);

        Log::channel('security')->info('password.changed', ['user_id' => $user->id, 'ip' => $request->ip()]);

        return response()->json(AccountResource::make($user)->resolve($request));
    }
}
