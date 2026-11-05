<?php

namespace App\Enums;

/**
 * Jenis transaksi stok (SPEC §10): Barang Masuk (+), Pemakaian (−), Koreksi (±, alasan wajib), Penghapusan (−, alasan wajib).
 */
enum StockTransactionType: string
{
    case BarangMasuk = 'Barang Masuk';
    case Pemakaian = 'Pemakaian';
    case Koreksi = 'Koreksi';
    case Penghapusan = 'Penghapusan';
}
