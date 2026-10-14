<?php

namespace App\Enums;

enum CountMethod: string
{
    case Qr = 'QR';
    case Manual = 'Manual';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
