<?php

use App\Support\AppCache;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * T30 R1: jenis transaksi ruangan bertambah Hilang, Rusak, Kedaluwarsa (alasan saat jumlah berkurang).
     * Juga membuang cache ringkasan kesiapan & data master, karena dua migration sebelumnya
     * (transaksi ruangan, penyelarasan slug) menulis lewat query builder sehingga observer cache tidak terpicu.
     */
    public function up(): void
    {
        $this->replaceTypeCheck(['Saldo awal', 'Diisi', 'Terpakai', 'Hilang', 'Rusak', 'Kedaluwarsa', 'Koreksi']);

        AppCache::flush(AppCache::GROUP_MASTER, AppCache::GROUP_OPS);
    }

    /**
     * Ditolak jika sudah ada transaksi berjenis baru (CHECK lama akan gagal); hapus manual dulu bila perlu.
     */
    public function down(): void
    {
        $this->replaceTypeCheck(['Saldo awal', 'Diisi', 'Terpakai', 'Koreksi']);
    }

    /**
     * @param  list<string>  $types
     */
    private function replaceTypeCheck(array $types): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $list = implode(', ', array_map(fn (string $type) => "'{$type}'", $types));

        DB::statement('ALTER TABLE room_stock_transactions DROP CONSTRAINT IF EXISTS room_stock_transactions_type_check');
        DB::statement("ALTER TABLE room_stock_transactions ADD CONSTRAINT room_stock_transactions_type_check CHECK (type IN ({$list}))");
    }
};
