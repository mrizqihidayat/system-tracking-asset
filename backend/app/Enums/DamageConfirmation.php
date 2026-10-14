<?php

namespace App\Enums;

enum DamageConfirmation: string
{
    case MenungguKonfirmasi = 'Menunggu Konfirmasi';
    case Dikonfirmasi = 'Dikonfirmasi';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
