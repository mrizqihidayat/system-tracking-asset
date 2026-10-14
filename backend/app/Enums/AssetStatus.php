<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Tersedia = 'Tersedia';
    case Dipinjam = 'Dipinjam';
    case Perbaikan = 'Perbaikan';
    case Hilang = 'Hilang';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
