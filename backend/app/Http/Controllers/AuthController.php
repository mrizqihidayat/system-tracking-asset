<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    /**
     * Hash bcrypt dari teks acak yang tidak disimpan, cost sama dengan BCRYPT_ROUNDS production. Dicek saat
     * username tidak ada agar waktu respons sama dengan password salah. Hash::make() per request tidak bisa
     * dipakai: membuat hash lalu mengeceknya butuh dua kali lipat waktu, sehingga username tetap bisa ditebak.
     */
    public const DUMMY_PASSWORD_HASH = '$2y$12$/EiSREyNv2h5l53DfYHk.Oh2/aftrBei6rhmUff4VYP.xjypXGnRm';

    public function login(Request $request)
    {
        $data = $request->validate([
            // Regex dengan flag u menolak karakter NUL dan teks bukan UTF-8 sebelum sampai ke database.
            'username' => ['required', 'string', 'max:50', 'regex:/^[^\x00]*$/u'],
            'password' => 'required|string',
        ]);

        $user = User::where('username', $data['username'])->first();
        $passwordMatches = Hash::check($data['password'], $user?->password ?? self::DUMMY_PASSWORD_HASH);

        if (! $user || ! $passwordMatches) {
            Log::channel('security')->info('login.failed', ['username' => $data['username'], 'ip' => $request->ip()]);

            return response()->json(['message' => 'Username atau password salah'], 401);
        }

        $token = $user->createToken('web')->plainTextToken;

        Log::channel('security')->info('login.success', ['user_id' => $user->id, 'username' => $user->username, 'ip' => $request->ip()]);

        return response()->json(['token' => $token, 'user' => $user]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json(['message' => 'Logout berhasil']);
    }

    public function me(Request $request)
    {
        return $request->user();
    }
}
