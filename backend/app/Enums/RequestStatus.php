<?php

namespace App\Enums;

enum RequestStatus: string
{
    case Diajukan = 'Diajukan';
    case Diterima = 'Diterima';
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
