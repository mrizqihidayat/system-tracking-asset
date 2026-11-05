<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Lokasi simpan stok kembali memilih ruangan (Gudang = ruangan biasa). Teks lama dicocokkan ke
     * "{ruangan}, {gedung}" lalu ke nama ruangan; yang tidak cocok atau cocok ke banyak ruangan menjadi kosong.
     */
    public function up(): void
    {
        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
                $table->index('room_id');
            });
        });

        DB::transaction(function () {
            $rooms = DB::table('rooms')->join('buildings', 'buildings.id', '=', 'rooms.building_id')
                ->get(['rooms.id', 'rooms.name', 'buildings.name as building']);
            $byFullName = $rooms->keyBy(fn ($room) => mb_strtolower("{$room->name}, {$room->building}"));
            $byName = $rooms->groupBy(fn ($room) => mb_strtolower($room->name));

            foreach (DB::table('consumables')->whereNotNull('storage_location')->get(['id', 'storage_location']) as $consumable) {
                $text = mb_strtolower(trim($consumable->storage_location));
                $sameName = $byName->get($text);
                $room = $byFullName->get($text) ?? ($sameName?->count() === 1 ? $sameName->first() : null);

                if ($room) {
                    DB::table('consumables')->where('id', $consumable->id)->update(['room_id' => $room->id]);
                }
            }
        });

        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropColumn('storage_location');
            });
        });
    }

    public function down(): void
    {
        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->string('storage_location', 150)->nullable();
            });
        });

        DB::transaction(function () {
            $rooms = DB::table('rooms')->join('buildings', 'buildings.id', '=', 'rooms.building_id')
                ->get(['rooms.id', 'rooms.name', 'buildings.name as building']);

            foreach ($rooms as $room) {
                DB::table('consumables')->where('room_id', $room->id)->update(['storage_location' => "{$room->name}, {$room->building}"]);
            }
        });

        $this->withoutNameIndexOnSqlite(function () {
            Schema::table('consumables', function (Blueprint $table) {
                $table->dropForeign(['room_id']);
                $table->dropIndex(['room_id']);
                $table->dropColumn('room_id');
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
