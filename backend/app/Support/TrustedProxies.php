<?php

namespace App\Support;

/**
 * Daftar reverse proxy yang dipercaya dari env TRUSTED_PROXIES. Hanya IP atau CIDR spesifik yang diterima:
 * "*", "**", "REMOTE_ADDR", dan "0.0.0.0/0" sama artinya dengan mempercayai semua pengirim, sehingga
 * pengunjung bisa memalsukan IP lewat X-Forwarded-For dan menghindari rate limit.
 */
class TrustedProxies
{
    /**
     * @return list<string>
     */
    public static function parse(?string $value): array
    {
        $entries = array_map(trim(...), explode(',', (string) $value));

        return array_values(array_filter($entries, self::isSpecific(...)));
    }

    private static function isSpecific(string $entry): bool
    {
        [$ip, $prefix] = array_pad(explode('/', $entry, 2), 2, null);

        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            return false;
        }

        if ($prefix === null) {
            return true;
        }

        $max = str_contains($ip, ':') ? 128 : 32;

        return ctype_digit($prefix) && (int) $prefix >= 1 && (int) $prefix <= $max;
    }
}
