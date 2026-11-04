<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi simpan stok menjadi teks bebas, diisi dari ruangan + gedung yang ada.
     */
    public function up(): void
    {
        Schema::table('consumables', function (Blueprint $table) {
            $table->string('storage_location', 150)->nullable();
        });

        DB::table('consumables')
            ->join('rooms', 'rooms.id', '=', 'consumables.room_id')
            ->join('buildings', 'buildings.id', '=', 'rooms.building_id')
            ->select('consumables.id', 'rooms.name as room_name', 'buildings.name as building_name')
            ->get()
            ->each(fn ($row) => DB::table('consumables')->where('id', $row->id)->update([
                'storage_location' => "{$row->room_name}, {$row->building_name}",
            ]));

        $this->withoutNameIndexOnSqlite(function () {
            if (Schema::hasIndex('consumables', 'consumables_room_id_index')) {
                Schema::table('consumables', fn (Blueprint $table) => $table->dropIndex('consumables_room_id_index'));
            }

            Schema::table('consumables', function (Blueprint $table) {
                $table->dropConstrainedForeignId('room_id');
            });
        });
    }

    /**
     * Struktur saja: teks lokasi tidak bisa dikembalikan menjadi tautan ruangan.
     */
    public function down(): void
    {
        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->foreignId('room_id')->nullable()->constrained('rooms');
                $table->index('room_id');
            });

            Schema::table('consumables', function (Blueprint $table) {
                $table->dropColumn('storage_location');
            });
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
