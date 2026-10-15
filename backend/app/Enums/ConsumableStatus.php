<?php

namespace App\Enums;

/**
 * Status stok habis pakai (dihitung, tidak disimpan; SPEC §10):
 * Habis = 0, Menipis = > 0 dan ≤ minimum, Aman = > minimum.
 */
enum ConsumableStatus: string
{
    case Aman = 'Aman';
    case Menipis = 'Menipis';
    case Habis = 'Habis';

    public static function fromQuantities(int $quantity, int $minimum): self
    {
        return match (true) {
            $quantity <= 0 => self::Habis,
            $quantity <= $minimum => self::Menipis,
            default => self::Aman,
        };
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
