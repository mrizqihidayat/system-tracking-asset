<?php

namespace App\Enums;

enum AssetCondition: string
{
    case Baik = 'Baik';
    case RusakRingan = 'Rusak Ringan';
    case RusakBerat = 'Rusak Berat';

    /**
     * Status aset dari kondisi (SPEC §5; dipakai tambah aset, lapor rusak, terima kembali):
     * Baik → Tersedia, Rusak Berat → Perbaikan, Rusak Ringan → pilihan staf (default Tersedia).
     */
    public function resolveStatus(?AssetStatus $chosen = null): AssetStatus
    {
        return match ($this) {
            self::Baik => AssetStatus::Tersedia,
            self::RusakBerat => AssetStatus::Perbaikan,
            self::RusakRingan => $chosen === AssetStatus::Perbaikan ? AssetStatus::Perbaikan : AssetStatus::Tersedia,
        };
    }

    /**
     * Hanya Rusak Ringan yang statusnya boleh dipilih staf.
     */
    public function allowsStatusChoice(): bool
    {
        return $this === self::RusakRingan;
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
