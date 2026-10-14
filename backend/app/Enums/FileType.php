<?php

namespace App\Enums;

enum FileType: string
{
    case FotoAset = 'Foto Aset';
    case FotoKondisi = 'Foto Kondisi';
    case Bast = 'BAST';
    case Garansi = 'Garansi';
    case Lainnya = 'Lainnya';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
