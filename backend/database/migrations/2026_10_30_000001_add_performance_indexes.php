<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index untuk kolom FK/filter/urutan yang belum ber-index (PostgreSQL tidak membuat index FK otomatis):
 * filter & urutan daftar, hitungan QR kesiapan ruangan, dan cek pemakaian sebelum hapus data master.
 */
return new class extends Migration
{
    /**
     * @var array<string, list<string|list<string>>>
     */
    private array $indexes = [
        'assets' => [['room_id', 'asset_type_id']],
        'loans' => ['destination_room_id', 'return_room_id', 'borrow_date', 'damage_confirmation'],
        'asset_events' => ['from_room_id', 'to_room_id'],
        'consumables' => ['room_id', 'catalog_id'],
        'supply_requests' => ['room_id', 'catalog_id', 'request_date'],
        'room_standards' => ['asset_type_id', 'consumable_id', 'catalog_id'],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->index($column);
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $columns) {
            Schema::table($table, function (Blueprint $blueprint) use ($columns) {
                foreach ($columns as $column) {
                    $blueprint->dropIndex((array) $column);
                }
            });
        }
    }
};
