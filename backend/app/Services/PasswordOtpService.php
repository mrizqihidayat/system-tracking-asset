<?php

namespace App\Services;

use App\Mail\PasswordOtpMail;
use App\Models\PasswordOtp;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Throwable;

/**
 * Lupa password via OTP (T40, SPEC §15): minta OTP → cek OTP → buat password baru.
 * - OTP 6 digit dan token reset hanya disimpan sebagai hash, sekali pakai.
 * - Maksimal 5 kali salah per OTP, kirim ulang paling cepat tiap 60 detik (dicatat di tabel, bukan cache),
 *   masa berlaku dari Pengaturan > Keamanan.
 * - Tidak ada jawaban yang membedakan email terdaftar dan tidak terdaftar.
 */
class PasswordOtpService
{
    public const MAX_ATTEMPTS = 5;

    public const RESEND_SECONDS = 60;

    /** Setelah OTP benar, password baru harus dibuat dalam waktu ini. */
    public const RESET_MINUTES = 10;

    public function __construct(
        private SettingService $settings,
        private AuthService $auth,
        private LoginLockout $lockout,
    ) {}

    /**
     * Buat dan kirim OTP bila email milik akun aktif dan kiriman terakhir sudah lewat 60 detik. Dipanggil
     * setelah respons terkirim, jadi lama proses tidak membocorkan apakah email terdaftar.
     */
    public function issue(string $email, ?string $ip = null): void
    {
        $user = $this->findUser($email);
        if (! $user) {
            return;
        }

        $otp = DB::transaction(function () use ($user): ?string {
            $row = PasswordOtp::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if ($row && $row->last_sent_at->gt(now()->subSeconds(self::RESEND_SECONDS))) {
                return null;
            }

            $otp = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

            PasswordOtp::query()->updateOrCreate(['user_id' => $user->id], [
                'otp_hash' => Hash::make($otp),
                'attempts' => 0,
                'expires_at' => now()->addMinutes($this->settings->int('otp_ttl_minutes')),
                'last_sent_at' => now(),
                'used_at' => null,
                'reset_token_hash' => null,
                'reset_expires_at' => null,
            ]);

            return $otp;
        });

        if ($otp === null) {
            return;
        }

        Log::channel('security')->info('password_otp.sent', ['user_id' => $user->id, 'ip' => $ip]);
        $this->send($user, $otp);
    }

    /**
     * MAIL_MAILER=log menulis isi email (termasuk OTP) ke storage/logs: hanya boleh di lingkungan lokal.
     * Di lingkungan lain email tidak dikirim dan kesalahan konfigurasi dicatat tanpa OTP.
     */
    private function send(User $user, string $otp): void
    {
        if (config('mail.default') === 'log' && ! app()->environment('local', 'testing')) {
            Log::error('password_otp.mailer_not_configured: MAIL_MAILER=log hanya untuk APP_ENV=local. Isi MAIL_* dengan SMTP.');

            return;
        }

        try {
            Mail::to($user->email)->send(new PasswordOtpMail(
                $otp,
                $this->settings->int('otp_ttl_minutes'),
                $this->settings->text('site_title'),
                $this->settings->publicPayload()['logo_url'],
            ));
        } catch (Throwable $e) {
            // Pesan kesalahan SMTP saja; OTP dan alamat email tidak dicatat.
            Log::error('password_otp.send_failed: '.$e::class);
        }
    }

    /**
     * Cek OTP. Benar → OTP hangus dan token reset sekali pakai dikembalikan; selain itu null (salah, kedaluwarsa,
     * sudah dipakai, sudah 5 kali salah, atau email tidak terdaftar: semuanya dijawab sama).
     */
    public function verify(string $email, string $otp): ?string
    {
        $user = $this->findUser($email);

        if (! $user) {
            // Samakan waktu respons dengan pengecekan hash.
            Hash::check($otp, AuthService::DUMMY_PASSWORD_HASH);

            return null;
        }

        [$token, $row] = DB::transaction(function () use ($user, $otp): array {
            $row = PasswordOtp::query()->where('user_id', $user->id)->lockForUpdate()->first();

            if (! $row || $row->used_at || $row->expires_at->isPast() || $row->attempts >= self::MAX_ATTEMPTS) {
                Hash::check($otp, AuthService::DUMMY_PASSWORD_HASH);

                return [null, null];
            }

            if (! Hash::check($otp, $row->otp_hash)) {
                $row->increment('attempts');

                return [null, $row];
            }

            $token = Str::random(64);
            $row->update([
                'used_at' => now(),
                'reset_token_hash' => hash('sha256', $token),
                'reset_expires_at' => now()->addMinutes(self::RESET_MINUTES),
            ]);

            return [$token, $row];
        });

        if ($token === null && $row) {
            Log::channel('security')->info('password_otp.wrong', ['user_id' => $user->id, 'attempts' => $row->attempts]);
        }

        return $token;
    }

    /** Akun pemilik token reset yang masih berlaku, atau null. */
    public function userForResetToken(string $email, string $token): ?User
    {
        $user = $this->findUser($email);
        $row = $user ? PasswordOtp::query()->where('user_id', $user->id)->first() : null;

        $valid = $row && $row->reset_token_hash && $row->reset_expires_at?->isFuture()
            && hash_equals($row->reset_token_hash, hash('sha256', $token));

        return $valid ? $user : null;
    }

    /**
     * Simpan password baru: token reset hangus, semua sesi dicabut, kunci salah password dibuka, dan
     * kewajiban ganti password (setelah reset staf) dianggap terpenuhi.
     */
    public function resetPassword(User $user, string $password, ?string $ip = null): void
    {
        DB::transaction(function () use ($user, $password): void {
            PasswordOtp::query()->where('user_id', $user->id)->delete();
            $user->forceFill(['password' => $password, 'must_change_password' => false, 'password_changed_at' => now()])->save();
            $user->tokens()->delete();
        });

        $this->lockout->clear($user->loginId());
        Log::channel('security')->info('password.reset_by_otp', ['user_id' => $user->id, 'ip' => $ip]);
    }

    /** Akun aktif (bukan nonaktif) dengan email itu. */
    private function findUser(string $email): ?User
    {
        return User::query()->where('email', mb_strtolower(trim($email)))->first();
    }
}
