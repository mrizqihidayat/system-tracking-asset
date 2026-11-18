<?php

namespace App\Http\Controllers;

use App\Http\Resources\AccountResource;
use App\Services\AuthService;
use App\Services\PasswordOtpService;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * "Lupa password?" untuk pengguna yang sudah login (T42, SPEC §15): kirim OTP ke email akun → cek OTP →
 * password baru tanpa password lama. Email lengkap tidak pernah ada di respons.
 */
class UserPasswordOtpController extends Controller
{
    private const NO_EMAIL = 'Akun ini belum memiliki email. Minta pegawai SHD mereset password melalui Pengaturan > Akun.';

    public function __construct(
        private PasswordOtpService $otps,
        private SettingService $settings,
        private AuthService $auth,
    ) {}

    public function send(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user->email === null) {
            return $this->noEmail();
        }

        $result = $this->otps->issueForUser($user, $request->ip());

        if ($result['status'] === 'wait') {
            return response()->json([
                'message' => "Kode baru bisa diminta dalam {$result['seconds']} detik.",
                'code' => 'otp_resend_wait',
                'resend_after_seconds' => $result['seconds'],
            ], 429, ['Retry-After' => $result['seconds']]);
        }

        if ($result['status'] === 'failed') {
            return response()->json(['message' => 'Kode gagal dikirim ke email Anda. Coba lagi beberapa saat lagi.', 'code' => 'otp_send_failed'], 503);
        }

        return response()->json([
            'message' => 'Kode verifikasi sudah dikirim ke email akun Anda.',
            'resend_after_seconds' => PasswordOtpService::RESEND_SECONDS,
            'expires_in_minutes' => $this->settings->int('otp_ttl_minutes'),
        ]);
    }

    public function verify(Request $request): JsonResponse
    {
        $otp = $request->validate(['otp' => ['required', 'digits:6']], [
            'otp.required' => 'Kode OTP wajib diisi.',
            'otp.digits' => 'Kode OTP terdiri dari 6 angka.',
        ])['otp'];

        $user = $request->user();

        if ($user->email === null) {
            return $this->noEmail();
        }

        $result = $this->otps->verifyForUser($user, (string) $otp);

        if ($result['token'] === null) {
            throw ValidationException::withMessages(['otp' => $this->failureMessage($result['reason'], $result['remaining'])]);
        }

        return response()->json(['reset_token' => $result['token'], 'expires_in_minutes' => PasswordOtpService::RESET_MINUTES]);
    }

    public function reset(Request $request): JsonResponse
    {
        $user = $request->user();
        $token = $request->validate(['reset_token' => ['required', 'string', 'size:64']])['reset_token'];

        if (! $this->otps->userHasResetToken($user, $token)) {
            throw ValidationException::withMessages(['reset_token' => 'Sesi verifikasi sudah tidak berlaku. Minta kode baru.']);
        }

        $password = $request->validate(
            ['password' => $this->auth->newPasswordRules($user)],
            [],
            ['password' => 'password baru'],
        )['password'];

        $this->otps->resetPasswordKeepingSession($user, $password, $request->ip());

        return response()->json(AccountResource::make($user->refresh())->resolve($request));
    }

    private function noEmail(): JsonResponse
    {
        return response()->json(['message' => self::NO_EMAIL, 'code' => 'no_email'], 422);
    }

    private function failureMessage(?string $reason, int $remaining): string
    {
        return match ($reason) {
            'expired' => 'Kode sudah kedaluwarsa. Minta kode baru.',
            'locked' => 'Terlalu banyak percobaan salah. Minta kode baru.',
            'used' => 'Kode sudah dipakai. Minta kode baru.',
            'none' => 'Belum ada kode yang dikirim. Minta kode dulu.',
            default => $remaining > 0
                ? "Kode salah. Sisa percobaan: {$remaining}."
                : 'Kode salah dan percobaan habis. Minta kode baru.',
        };
    }
}
