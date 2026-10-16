<?php

namespace App\Enums;

/**
 * Hasil baris checklist kelengkapan ruangan (dihitung, tidak disimpan; SPEC §8).
 * Belum dicek = baris Manual dengan actual_quantity kosong: tidak dihitung kurang.
 */
enum ChecklistResult: string
{
    case Sesuai = 'Sesuai';
    case Kurang = 'Kurang';
    case BelumDicek = 'Belum dicek';

    public static function fromQuantities(int $standard, ?int $actual): self
    {
        return match (true) {
            $actual === null => self::BelumDicek,
            $actual < $standard => self::Kurang,
            default => self::Sesuai,
        };
    }
}
