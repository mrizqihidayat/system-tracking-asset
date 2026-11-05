<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const NEW_CHECK = "(count_method = 'QR' AND asset_type_id IS NOT NULL AND consumable_id IS NULL) OR (count_method = 'Manual' AND consumable_id IS NOT NULL AND asset_type_id IS NULL)";

    /**
     * Kelengkapan ruangan dua bagian: baris standar hanya per jenis aset atau per barang stok.
     * Barang departemen lain pindah ke Stok, baris "Perlu dilengkapi" dihapus, dan baris ganda digabung.
     */
    public function up(): void
    {
        $this->moveOtherDepartmentItemsToStock();
        $this->mergeDuplicates('asset_type_id');
        $this->mergeDuplicates('consumable_id');

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE room_standards DROP CONSTRAINT IF EXISTS room_standards_fulfillment_check');
            DB::statement('ALTER TABLE room_standards DROP CONSTRAINT IF EXISTS room_standards_qr_asset_type_check');
        }

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropForeign(['room_standard_id']);
            $table->dropColumn('room_standard_id');

            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('status', ['Diajukan', 'Diterima', 'Dikembalikan', 'Dibatalkan'])->default('Diajukan')->change();
            }
        });

        Schema::table('room_standards', function (Blueprint $table) {
            $table->dropForeign(['source_department_id']);
            $table->dropForeign(['catalog_id']);
            $table->dropForeign(['consumable_id']);
            $table->dropIndex(['catalog_id']);
            $table->dropColumn(['item_name', 'source_department_id', 'catalog_id', 'fulfillment_note']);
            $table->foreign('consumable_id')->references('id')->on('consumables')->cascadeOnDelete();
            $table->unique(['room_id', 'asset_type_id']);
            $table->unique(['room_id', 'consumable_id']);

            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('count_method', ['QR', 'Manual'])->change();
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE room_standards ADD CONSTRAINT room_standards_item_check CHECK ('.self::NEW_CHECK.')');
        }
    }

    /**
     * Struktur saja: baris yang digabung atau dihapus tidak kembali.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE room_standards DROP CONSTRAINT IF EXISTS room_standards_item_check');
        }

        Schema::table('room_standards', function (Blueprint $table) {
            $table->dropUnique(['room_id', 'asset_type_id']);
            $table->dropUnique(['room_id', 'consumable_id']);
            $table->dropForeign(['consumable_id']);
            $table->string('item_name', 150)->nullable();
            $table->foreignId('source_department_id')->nullable()->constrained('departments')->restrictOnDelete();
            $table->string('fulfillment_note')->nullable();
            $table->foreignId('catalog_id')->nullable()->constrained('request_catalog')->nullOnDelete();
            $table->index('catalog_id');
            $table->foreign('consumable_id')->references('id')->on('consumables')->nullOnDelete();

            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('count_method', ['QR', 'Manual'])->change();
            }
        });

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->foreignId('room_standard_id')->nullable()->constrained('room_standards')->nullOnDelete();

            if (DB::getDriverName() !== 'pgsql') {
                $table->enum('status', ['Diajukan', 'Diterima', 'Dikembalikan', 'Dibatalkan'])->default('Diajukan')->change();
            }
        });
    }

    /**
     * Baris Manual tanpa barang stok: bersumber departemen lain → menjadi barang Stok (jumlah 0, nama sama = barang
     * yang sudah ada), bersumber SHD → dihapus. Permintaan terkait ikut tertaut ke barangnya; yang sudah Diterima
     * tidak lagi punya tujuan untuk dikurangi karena penambahannya masuk ke ruangan, bukan stok.
     */
    private function moveOtherDepartmentItemsToStock(): void
    {
        DB::transaction(function () {
            $internal = DB::table('departments')->where('is_internal', true)->pluck('id')->all();

            $rows = DB::table('room_standards')->where('count_method', 'Manual')->whereNull('consumable_id')->orderBy('id')->get();

            foreach ($rows as $row) {
                if (in_array($row->source_department_id, $internal)) {
                    DB::table('supply_requests')->where('room_standard_id', $row->id)->update(['room_standard_id' => null, 'applied_to' => null]);
                    DB::table('room_standards')->where('id', $row->id)->delete();

                    continue;
                }

                $consumableId = DB::table('consumables')->whereRaw('lower(name) = ?', [mb_strtolower($row->item_name)])->value('id')
                    ?? DB::table('consumables')->insertGetId([
                        'name' => $row->item_name,
                        'unit' => 'pcs',
                        'quantity' => 0,
                        'min_quantity' => 0,
                        'provider_department_id' => $row->source_department_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                DB::table('room_standards')->where('id', $row->id)->update(['consumable_id' => $consumableId, 'catalog_id' => null, 'fulfillment_note' => null]);
                DB::table('supply_requests')->where('room_standard_id', $row->id)->whereNull('consumable_id')->update(['consumable_id' => $consumableId]);
                DB::table('supply_requests')->where('room_standard_id', $row->id)->where('applied_to', 'ruangan')->update(['applied_to' => null]);
            }
        });
    }

    /**
     * Satu standar per ruangan & item: baris ganda digabung ke baris tertua (jumlah standar dan Ada dijumlahkan).
     */
    private function mergeDuplicates(string $column): void
    {
        DB::transaction(function () use ($column) {
            $rows = DB::table('room_standards')->whereNotNull($column)->orderBy('id')->get()->groupBy(fn ($row) => "{$row->room_id}:{$row->{$column}}");

            foreach ($rows as $group) {
                if ($group->count() < 2) {
                    continue;
                }

                $present = $group->pluck('actual_quantity')->filter(fn ($quantity) => $quantity !== null);

                DB::table('room_standards')->where('id', $group->first()->id)->update([
                    'standard_quantity' => $group->sum('standard_quantity'),
                    'actual_quantity' => $present->isEmpty() ? null : $present->sum(),
                ]);
                DB::table('room_standards')->whereIn('id', $group->skip(1)->pluck('id'))->delete();
            }
        });
    }
};
