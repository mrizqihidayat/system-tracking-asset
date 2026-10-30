<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Cache berversi per grup (tanpa tag, jadi jalan di store file/database/redis). flush() cukup mengganti
 * versi grup; kunci lama tidak terpakai lagi dan kedaluwarsa sendiri lewat TTL.
 *
 * - GROUP_MASTER: daftar data master untuk combobox (berubah jarang).
 * - GROUP_OPS: ringkasan operasional (dashboard, kesiapan ruangan, saran permintaan).
 *
 * Invalidasi otomatis lewat CacheInvalidationObserver (lihat AppServiceProvider).
 */
class AppCache
{
    public const GROUP_MASTER = 'master';

    public const GROUP_OPS = 'ops';

    /** TTL detik per grup (jaring pengaman jika ada perubahan yang tidak lewat model). */
    private const TTL = [
        self::GROUP_MASTER => 3600,
        self::GROUP_OPS => 600,
    ];

    /**
     * @template T
     *
     * @param  Closure(): T  $callback
     * @return T
     */
    public static function remember(string $group, string $key, Closure $callback): mixed
    {
        return Cache::remember(self::key($group, $key), self::TTL[$group], $callback);
    }

    public static function flush(string ...$groups): void
    {
        foreach ($groups as $group) {
            Cache::forever(self::versionKey($group), Str::random(12));
        }
    }

    private static function key(string $group, string $key): string
    {
        $version = Cache::rememberForever(self::versionKey($group), fn () => Str::random(12));

        return "app:{$group}:{$version}:{$key}";
    }

    private static function versionKey(string $group): string
    {
        return "app:{$group}:version";
    }
}
