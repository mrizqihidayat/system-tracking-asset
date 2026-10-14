<?php

namespace App\Enums;

enum RequestMethod: string
{
    case Memo = 'Memo';
    case Form = 'Form';
    case Email = 'Email';
    case Lainnya = 'Lainnya';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
