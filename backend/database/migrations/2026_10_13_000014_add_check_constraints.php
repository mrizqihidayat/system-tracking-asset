<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * CHECK constraint aturan bisnis (hanya PostgreSQL; enum sudah menjadi CHECK lewat Schema builder).
     *
     * @var array<string, array<string, string>>
     */
    private array $constraints = [
        'loans' => [
            'loans_due_date_check' => 'due_date >= borrow_date',
        ],
        'request_catalog' => [
            'request_catalog_lead_time_days_check' => 'lead_time_days >= 0',
        ],
        'consumables' => [
            'consumables_quantity_check' => 'quantity >= 0',
            'consumables_min_quantity_check' => 'min_quantity >= 0',
        ],
        'supply_requests' => [
            'supply_requests_quantity_check' => 'quantity > 0',
        ],
        'room_standards' => [
            'room_standards_standard_quantity_check' => 'standard_quantity > 0',
            'room_standards_actual_quantity_check' => 'actual_quantity IS NULL OR actual_quantity >= 0',
            'room_standards_qr_asset_type_check' => "count_method <> 'QR' OR asset_type_id IS NOT NULL",
            'room_standards_fulfillment_check' => 'num_nonnulls(consumable_id, fulfillment_note, catalog_id) <= 1',
        ],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints as $table => $checks) {
            foreach ($checks as $name => $expression) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$name} CHECK ({$expression})");
            }
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        foreach ($this->constraints as $table => $checks) {
            foreach (array_keys($checks) as $name) {
                DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$name}");
            }
        }
    }
};
