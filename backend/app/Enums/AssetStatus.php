<?php

namespace App\Enums;

enum AssetStatus: string
{
    case Tersedia = 'Tersedia';
    case Dipinjam = 'Dipinjam';
    case Perbaikan = 'Perbaikan';
    case Hilang = 'Hilang';
    case TidakLayakPakai = 'Tidak Layak Pakai';

    /**
     * Aksi yang valid untuk status ini (SPEC §6), dicatat staf SHD lewat endpoint ber-auth:
     * - Tersedia: pinjam, pindah lokasi, catat kerusakan, catat kehilangan, catat tidak layak pakai
     * - Dipinjam: terima kembali, pindah lokasi, catat kerusakan (kehilangan lewat terima kembali)
     * - Perbaikan: pindah lokasi, catat kerusakan, catat kehilangan, catat tidak layak pakai
     * - Hilang: tandai ditemukan, pindah lokasi
     * - Tidak Layak Pakai: pindah lokasi saja (tidak ada aksi untuk mengembalikannya)
     * "Hapus aset" berlaku untuk semua status dan tidak termasuk daftar ini.
     *
     * @return list<string>
     */
    public function allowedActions(): array
    {
        return match ($this) {
            self::Tersedia => ['pinjam', 'pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai'],
            self::Dipinjam => ['terima_kembali', 'pindah_lokasi', 'catat_kerusakan'],
            self::Perbaikan => ['pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai'],
            self::Hilang => ['tandai_ditemukan', 'pindah_lokasi'],
            self::TidakLayakPakai => ['pindah_lokasi'],
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
