<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Stok habis pakai tidak lagi tertaut katalog; katalog hanya untuk baris standar Barang departemen lain (T26r).
     */
    public function up(): void
    {
        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropForeign(['catalog_id']);
                $table->dropIndex(['catalog_id']);
                $table->dropColumn('catalog_id');
            });
        });
    }

    /**
     * Struktur saja: tautan stok ke katalog yang sudah dihapus tidak dikembalikan.
     */
    public function down(): void
    {
        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->unsignedBigInteger('catalog_id')->nullable();
                $table->index('catalog_id');
                $table->foreign('catalog_id')->references('id')->on('request_catalog')->nullOnDelete();
            });
        });
    }

    /**
     * SQLite (test) membangun ulang tabel saat FK/kolom berubah dan tidak bisa membawa unique index lower(name).
     */
    private function withoutNameIndexOnSqlite(callable $change): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite) {
            DB::statement('DROP INDEX IF EXISTS consumables_name_lower_unique');
        }

        $change();

        if ($sqlite) {
            DB::statement('CREATE UNIQUE INDEX consumables_name_lower_unique ON consumables (lower(name))');
        }
    }
};
