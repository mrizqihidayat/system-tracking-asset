<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * Penguncian akun setelah salah password berkali-kali (T40, SPEC §15). Dihitung per NIK/username (tanpa beda
 * huruf besar/kecil), bukan per IP, dan berlaku sama untuk NIK/username yang tidak terdaftar supaya respons
 * tidak membocorkan akun mana yang ada. Batas dan lama kunci dari Pengaturan > Keamanan.
 */
class LoginLockout
{
    public function __construct(private SettingService $settings) {}

    /** Sisa detik terkunci; 0 = tidak terkunci. */
    public function secondsLocked(string $loginId): int
    {
        $until = (int) Cache::get($this->lockKey($loginId), 0);

        return max(0, $until - now()->getTimestamp());
    }

    /** Catat satu kali salah password; mengembalikan true bila akun menjadi terkunci. */
    public function recordFailure(string $loginId): bool
    {
        $seconds = $this->settings->int('lockout_minutes') * 60;
        $key = $this->countKey($loginId);

        // Hitungan salah berlaku selama satu masa kunci sejak salah yang pertama.
        Cache::add($key, 0, $seconds);
        $failures = (int) Cache::increment($key);

        if ($failures < $this->settings->int('max_login_attempts')) {
            return false;
        }

        Cache::put($this->lockKey($loginId), now()->getTimestamp() + $seconds, $seconds);
        Cache::forget($key);

        return true;
    }

    /** Hapus hitungan dan kunci: login berhasil, reset oleh staf SHD, atau password diganti lewat OTP. */
    public function clear(string $loginId): void
    {
        Cache::forget($this->countKey($loginId));
        Cache::forget($this->lockKey($loginId));
    }

    private function countKey(string $loginId): string
    {
        return 'login-failures:'.$this->hash($loginId);
    }

    private function lockKey(string $loginId): string
    {
        return 'login-locked:'.$this->hash($loginId);
    }

    private function hash(string $loginId): string
    {
        return sha1(mb_strtolower(trim($loginId)));
    }
}
