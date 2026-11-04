<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Barang "tidak disimpan SHD" keluar dari stok: baris standarnya menjadi tipe Barang unit lain (Manual tanpa
     * consumable_id, sumber = penyedia, katalog tetap tertaut), permintaannya tertaut ke baris standar itu.
     */
    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('consumables')->where('is_stored', false)->get() as $consumable) {
                $requests = DB::table('supply_requests')->where('consumable_id', $consumable->id)->get();

                foreach ($requests as $request) {
                    $standardId = $request->room_standard_id ?? DB::table('room_standards')
                        ->where('consumable_id', $consumable->id)
                        ->where('room_id', $request->room_id)
                        ->orderBy('id')
                        ->value('id');

                    DB::table('supply_requests')->where('id', $request->id)->update([
                        'consumable_id' => null,
                        'room_standard_id' => $standardId,
                    ]);
                }

                DB::table('room_standards')->where('consumable_id', $consumable->id)->update([
                    'consumable_id' => null,
                    'source_department_id' => $consumable->provider_department_id,
                    'catalog_id' => $consumable->catalog_id,
                    'fulfillment_note' => null,
                ]);

                DB::table('consumables')->where('id', $consumable->id)->delete();
            }
        });

        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropColumn('is_stored');
            });
        });
    }

    /**
     * Struktur saja: barang yang sudah dipindahkan tidak dikembalikan ke stok.
     */
    public function down(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->boolean('is_stored')->default(true);
        });
    }

    /**
     * SQLite (test) membangun ulang tabel saat kolom dihapus dan tidak bisa membawa unique index lower(name).
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
