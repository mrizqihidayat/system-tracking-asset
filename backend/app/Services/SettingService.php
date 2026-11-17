<?php

namespace App\Services;

use App\Models\Setting;
use App\Models\SettingChange;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Pengaturan aplikasi (T40, SPEC §15): tampilan, konten, dan keamanan. Satu-satunya jalur baca/tulis tabel
 * `settings`. Key yang belum punya baris memakai DEFAULTS, jadi database tanpa seeder tetap jalan.
 * Setiap simpan menulis `setting_changes`, mencatat log `security`, dan mengosongkan cache.
 */
class SettingService
{
    public const DEFAULTS = [
        'theme_primary' => '#017cc2',
        'theme_sidebar' => '#0e4f7e',
        'site_title' => 'Aset SHD',
        'site_description' => 'Masuk untuk melanjutkan ke Sistem Informasi Aset',
        'footer_text' => 'Departemen SHD · PT INALUM',
        'report_header' => '',
        'label_text' => '',
        'logo_path' => null,
        'favicon_path' => null,
        'max_login_attempts' => 5,
        'lockout_minutes' => 15,
        'password_min_length' => 8,
        'otp_ttl_minutes' => 10,
    ];

    /** Jenis file yang bisa diunggah → key pengaturan yang menyimpan path-nya. */
    public const FILE_KEYS = ['logo' => 'logo_path', 'favicon' => 'favicon_path'];

    /** Key yang boleh dibaca tanpa login (`GET /settings/public`). */
    private const PUBLIC_KEYS = ['theme_primary', 'theme_sidebar', 'site_title', 'site_description', 'footer_text', 'password_min_length'];

    private const CACHE_KEY = 'app:settings';

    /**
     * Semua pengaturan (default ditimpa nilai tersimpan).
     *
     * @return array<string, mixed>
     */
    public function all(): array
    {
        $stored = Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all());

        return array_intersect_key($stored, self::DEFAULTS) + self::DEFAULTS;
    }

    public function get(string $key): mixed
    {
        return $this->all()[$key];
    }

    public function text(string $key): string
    {
        return trim((string) $this->get($key));
    }

    public function int(string $key): int
    {
        return (int) $this->get($key);
    }

    /**
     * Nilai untuk halaman Pengaturan: semua key selain path file, ditambah URL logo/favicon.
     *
     * @return array<string, mixed>
     */
    public function editable(): array
    {
        return array_diff_key($this->all(), array_flip(self::FILE_KEYS)) + $this->fileUrls();
    }

    /**
     * @return array<string, mixed>
     */
    public function defaults(): array
    {
        return array_diff_key(self::DEFAULTS, array_flip(self::FILE_KEYS));
    }

    /**
     * @return array<string, mixed>
     */
    public function publicPayload(): array
    {
        return array_intersect_key($this->all(), array_flip(self::PUBLIC_KEYS)) + $this->fileUrls();
    }

    /**
     * Siapa dan kapan pengaturan terakhir diubah.
     *
     * @return array{by: string|null, at: string}|null
     */
    public function lastChange(): ?array
    {
        $change = SettingChange::query()->latest('id')->first();

        return $change ? [
            'by' => User::withTrashed()->find($change->user_id)?->name,
            'at' => $change->created_at->toIso8601String(),
        ] : null;
    }

    /**
     * Simpan nilai yang berubah saja.
     *
     * @param  array<string, mixed>  $values
     * @return list<string> key yang berubah
     */
    public function update(array $values, User $actor, ?string $ip = null): array
    {
        $values = array_intersect_key($values, self::DEFAULTS);

        $changed = DB::transaction(function () use ($values, $actor): array {
            // Kunci baris yang ada supaya dua simpan bersamaan tidak mencatat nilai lama yang salah.
            $stored = Setting::query()->whereIn('key', array_keys($values))->lockForUpdate()->get()->keyBy('key');
            $changed = [];

            foreach ($values as $key => $value) {
                $old = $stored->has($key) ? $stored[$key]->value : self::DEFAULTS[$key];
                if ($old === $value) {
                    continue;
                }

                Setting::query()->updateOrCreate(['key' => $key], ['value' => $value, 'updated_by' => $actor->id]);
                SettingChange::create(['key' => $key, 'old_value' => $old, 'new_value' => $value, 'user_id' => $actor->id, 'created_at' => now()]);
                $changed[] = $key;
            }

            return $changed;
        });

        if ($changed !== []) {
            Cache::forget(self::CACHE_KEY);
            Log::channel('security')->info('settings.updated', ['actor_id' => $actor->id, 'keys' => $changed, 'ip' => $ip]);
        }

        return $changed;
    }

    /** Simpan logo/favicon baru ke ASSET_DISK; file lama dihapus setelah pengaturan tersimpan. */
    public function storeFile(string $type, UploadedFile $file, User $actor, ?string $ip = null): void
    {
        $key = self::FILE_KEYS[$type];
        $old = $this->get($key);
        $path = $file->storeAs('settings', $type.'-'.Str::random(16).'.'.$file->extension(), ['disk' => config('filesystems.asset_disk')]);

        try {
            $this->update([$key => $path], $actor, $ip);
        } catch (\Throwable $e) {
            $this->disk()->delete($path);

            throw $e;
        }

        if ($old) {
            $this->disk()->delete($old);
        }
    }

    public function deleteFile(string $type, User $actor, ?string $ip = null): void
    {
        $key = self::FILE_KEYS[$type];
        $old = $this->get($key);

        $this->update([$key => null], $actor, $ip);

        if ($old) {
            $this->disk()->delete($old);
        }
    }

    public function filePath(string $type): ?string
    {
        $path = $this->get(self::FILE_KEYS[$type]);

        return $path && $this->disk()->exists($path) ? $path : null;
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.asset_disk'));
    }

    /**
     * URL logo/favicon (null = pakai bawaan aplikasi). Parameter `v` berganti setiap file diganti, sehingga
     * browser boleh menyimpan file lama selamanya.
     *
     * @return array{logo_url: string|null, favicon_url: string|null}
     */
    private function fileUrls(): array
    {
        $urls = [];

        foreach (self::FILE_KEYS as $type => $key) {
            $path = $this->get($key);
            $urls[$type.'_url'] = $path ? route('settings.file', ['type' => $type, 'v' => substr(md5($path), 0, 8)]) : null;
        }

        return $urls;
    }
}
