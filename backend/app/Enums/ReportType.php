<?php

namespace App\Enums;

enum ReportType: string
{
    case Assets = 'assets';
    case Loans = 'loans';
    case Rooms = 'rooms';
    case Requests = 'requests';
    case Consumables = 'consumables';

    public function title(): string
    {
        return match ($this) {
            self::Assets => 'Laporan Aset',
            self::Loans => 'Laporan Peminjaman',
            self::Rooms => 'Laporan Kelengkapan Ruangan',
            self::Requests => 'Laporan Permintaan ke Departemen Lain',
            self::Consumables => 'Laporan Stok Habis Pakai',
        };
    }

    /**
     * Bagian nama file: laporan-{slug}-{yyyymmdd}.xlsx
     */
    public function slug(): string
    {
        return match ($this) {
            self::Assets => 'aset',
            self::Loans => 'peminjaman',
            self::Rooms => 'kelengkapan-ruangan',
            self::Requests => 'permintaan',
            self::Consumables => 'stok-habis-pakai',
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
