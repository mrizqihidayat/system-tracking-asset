<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Barang habis pakai: hard delete (tanpa deleted_at), nama unik tanpa membedakan huruf besar/kecil,
     * dan tautan standar ruangan dilepas otomatis (ON DELETE SET NULL) saat barang dihapus.
     */
    public function up(): void
    {
        DB::table('consumables')->whereNotNull('deleted_at')->pluck('id')->whenNotEmpty(function ($ids) {
            DB::table('room_standards')->whereIn('consumable_id', $ids)->update(['consumable_id' => null]);
            DB::table('consumables')->whereIn('id', $ids)->delete();
        });

        Schema::table('consumables', function (Blueprint $table) {
            $table->dropColumn('deleted_at');
        });

        DB::statement('CREATE UNIQUE INDEX consumables_name_lower_unique ON consumables (lower(name))');

        $this->setConsumableForeignKey('set null');
    }

    public function down(): void
    {
        $this->setConsumableForeignKey('restrict');

        DB::statement('DROP INDEX IF EXISTS consumables_name_lower_unique');

        Schema::table('consumables', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * SQLite: mengganti FK membangun ulang tabel dan menghapus CHECK enum, jadi count_method dipasang ulang.
     */
    private function setConsumableForeignKey(string $onDelete): void
    {
        Schema::table('room_standards', function (Blueprint $table) use ($onDelete) {
            $table->dropForeign(['consumable_id']);
            $table->foreign('consumable_id')->references('id')->on('consumables')->onDelete($onDelete);

            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('count_method', ['QR', 'Manual'])->change();
            }
        });
    }
};
