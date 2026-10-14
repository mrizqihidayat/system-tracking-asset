<?php

namespace App\Enums;

enum DeletionReason: string
{
    case TidakDigunakan = 'Tidak digunakan';
    case Dijual = 'Dijual';
    case Lainnya = 'Lainnya';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
