<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Katalog permintaan boleh dihapus walau dipakai (menggantikan T14 Q2): tautan catalog_id dilepas
 * (ON DELETE SET NULL). Permintaan menyimpan snapshot lead_time_days & must_return agar "Belum direspons"
 * dan aturan Dikembalikan tidak berubah saat katalog diedit/dihapus.
 */
return new class extends Migration
{
    private const TABLES = ['supply_requests', 'room_standards', 'consumables'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            $this->catalogForeignKey($table, 'set null');
        }

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->unsignedInteger('lead_time_days')->nullable()->after('request_method');
            $table->boolean('must_return')->default(false)->after('lead_time_days');
        });

        // Snapshot data lama dari katalog yang masih tertaut (tanpa katalog: lead time null, tidak perlu kembali).
        DB::table('supply_requests')->whereNotNull('catalog_id')->update([
            'lead_time_days' => DB::raw('(select lead_time_days from request_catalog where request_catalog.id = supply_requests.catalog_id)'),
            'must_return' => DB::raw('(select must_return from request_catalog where request_catalog.id = supply_requests.catalog_id)'),
        ]);
    }

    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropColumn(['lead_time_days', 'must_return']);
        });

        foreach (self::TABLES as $table) {
            $this->catalogForeignKey($table, 'restrict');
        }
    }

    /**
     * SQLite: mengganti FK membangun ulang tabel → CHECK enum hilang (dipasang ulang) dan index ekspresi
     * lower(name) di consumables tidak bisa disalin (dilepas lalu dibuat ulang). PostgreSQL tidak terpengaruh.
     */
    private function catalogForeignKey(string $table, string $onDelete): void
    {
        $sqlite = DB::getDriverName() === 'sqlite';

        if ($sqlite && $table === 'consumables') {
            DB::statement('DROP INDEX IF EXISTS consumables_name_lower_unique');
        }

        Schema::table($table, function (Blueprint $blueprint) use ($table, $onDelete, $sqlite) {
            $blueprint->dropForeign(['catalog_id']);
            $blueprint->foreign('catalog_id')->references('id')->on('request_catalog')->onDelete($onDelete);

            if ($sqlite && $table === 'supply_requests') {
                $blueprint->enum('status', ['Diajukan', 'Diterima', 'Dikembalikan', 'Dibatalkan'])->default('Diajukan')->change();
            }

            if ($sqlite && $table === 'room_standards') {
                $blueprint->enum('count_method', ['QR', 'Manual'])->change();
            }
        });

        if ($sqlite && $table === 'consumables') {
            DB::statement('CREATE UNIQUE INDEX consumables_name_lower_unique ON consumables (lower(name))');
        }
    }
};
