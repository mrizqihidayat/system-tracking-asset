<?php

namespace App\Enums;

enum LoanStatus: string
{
    /** Tanggal pinjam di masa depan: aset tetap Tersedia sampai diserahkan (T31). */
    case Direncanakan = 'Direncanakan';

    case Dipinjam = 'Dipinjam';
    case Dikembalikan = 'Dikembalikan';
    case Dibatalkan = 'Dibatalkan';
    case Hilang = 'Hilang';

    /**
     * Status yang memakai aset pada suatu periode (dicek bentrok saat pinjam/pesan/serahkan).
     *
     * @return list<self>
     */
    public static function occupying(): array
    {
        return [self::Direncanakan, self::Dipinjam];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
