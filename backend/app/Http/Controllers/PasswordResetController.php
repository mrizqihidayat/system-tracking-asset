<?php

namespace App\Http\Controllers;

use App\Services\AuthService;
use App\Services\PasswordOtpService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

use function Illuminate\Support\defer;

/**
 * Lupa password via OTP (T40, SPEC §15), tanpa login: minta OTP → cek OTP → buat password baru.
 * Tidak ada jawaban yang membocorkan apakah email terdaftar.
 */
class PasswordResetController extends Controller
{
    private const EMAIL_RULES = ['required', 'string', 'max:150', 'email:rfc'];

    private const MESSAGES = [
        'email.required' => 'Email wajib diisi.',
        'email.email' => 'Format email tidak valid.',
        'email.max' => 'Format email tidak valid.',
        'otp.required' => 'Kode OTP wajib diisi.',
        'otp.digits' => 'Kode OTP terdiri dari 6 angka.',
    ];

    public function __construct(
        private PasswordOtpService $otps,
        private SettingService $settings,
        private AuthService $auth,
    ) {}

    public function forgot(Request $request): JsonResponse
    {
        $email = $request->validate(['email' => self::EMAIL_RULES], self::MESSAGES)['email'];
        $ip = $request->ip();

        // Dijalankan setelah respons terkirim: akun terdaftar dan tidak terdaftar dijawab sama cepatnya.
        defer(fn () => $this->otps->issue($email, $ip));

        return response()->json([
            'message' => 'Jika email terdaftar, kode OTP sudah dikirim ke email itu.',
            'resend_after_seconds' => PasswordOtpService::RESEND_SECONDS,
            'expires_in_minutes' => $this->settings->int('otp_ttl_minutes'),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => self::EMAIL_RULES, 'otp' => ['required', 'digits:6']], self::MESSAGES);

        $token = $this->otps->verify($data['email'], (string) $data['otp']);

        if ($token === null) {
            throw ValidationException::withMessages(['otp' => 'Kode OTP salah atau sudah tidak berlaku. Periksa lagi atau minta kode baru.']);
        }

        return response()->json(['reset_token' => $token, 'expires_in_minutes' => PasswordOtpService::RESET_MINUTES]);
    }

    public function reset(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => self::EMAIL_RULES, 'reset_token' => ['required', 'string', 'size:64']], self::MESSAGES);

        $user = $this->otps->userForResetToken($data['email'], $data['reset_token']);

        if (! $user) {
            throw ValidationException::withMessages(['reset_token' => 'Sesi reset sudah tidak berlaku. Minta kode OTP baru.']);
        }

        $password = $request->validate(
            ['password' => $this->auth->newPasswordRules($user)],
            [],
            ['password' => 'password baru'],
        )['password'];

        $this->otps->resetPassword($user, $password, $request->ip());

        return response()->json(['message' => 'Password berhasil diganti. Silakan masuk dengan password baru.']);
    }
}
