<?php

namespace App\Enums;

enum LoanStatus: string
{
    case Dipinjam = 'Dipinjam';
    case Dikembalikan = 'Dikembalikan';
    case Dibatalkan = 'Dibatalkan';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
