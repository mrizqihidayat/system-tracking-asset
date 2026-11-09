<?php

namespace App\Enums;

/**
 * Jenis transaksi barang habis pakai di ruangan (SPEC §8): Saldo awal (catatan pertama / migrasi), Diisi (+, Isi dari stok),
 * Terpakai/Hilang/Rusak/Kedaluwarsa (−, alasan dipilih staf saat jumlah berkurang), Koreksi (+, keterangan wajib).
 */
enum RoomStockTransactionType: string
{
    case SaldoAwal = 'Saldo awal';
    case Diisi = 'Diisi';
    case Terpakai = 'Terpakai';
    case Hilang = 'Hilang';
    case Rusak = 'Rusak';
    case Kedaluwarsa = 'Kedaluwarsa';
    case Koreksi = 'Koreksi';

    /**
     * Alasan yang bisa dipilih staf saat jumlah berkurang.
     *
     * @return list<self>
     */
    public static function decreaseReasons(): array
    {
        return [self::Terpakai, self::Hilang, self::Rusak, self::Kedaluwarsa];
    }
}
