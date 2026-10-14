<?php

namespace App\Enums;

enum EventType: string
{
    case Registrasi = 'Registrasi';
    case Dipinjam = 'Dipinjam';
    case Dikembalikan = 'Dikembalikan';
    case Dibatalkan = 'Dibatalkan';
    case PindahLokasi = 'Pindah Lokasi';
    case LaporRusak = 'Lapor Rusak';
    case LaporHilang = 'Lapor Hilang';
    case Servis = 'Servis';
    case UbahData = 'Ubah Data';
    case Dihapus = 'Dihapus';

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
