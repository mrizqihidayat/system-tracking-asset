<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE_STATUSES = ['Diajukan', 'Diterima', 'Dikembalikan', 'Dibatalkan'];

    /**
     * Status "Diterima sebagian" dan jumlah yang sudah masuk stok; received_quantity menggantikan applied_to.
     */
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->integer('received_quantity')->default(0);
        });

        DB::table('supply_requests')->where('applied_to', 'stok')->update(['received_quantity' => DB::raw('quantity')]);

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropColumn('applied_to');
        });

        $this->setStatuses([...self::BASE_STATUSES, 'Diterima sebagian']);

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE supply_requests ADD CONSTRAINT supply_requests_received_quantity_check CHECK (received_quantity >= 0)');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS supply_requests_received_quantity_check');
        }

        DB::table('supply_requests')->where('status', 'Diterima sebagian')->update(['status' => 'Diterima']);
        $this->setStatuses(self::BASE_STATUSES);

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->string('applied_to', 10)->nullable();
        });

        DB::table('supply_requests')->where('received_quantity', '>', 0)->update(['applied_to' => 'stok']);

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropColumn('received_quantity');
        });
    }

    /**
     * @param  list<string>  $statuses
     */
    private function setStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $status) => "'{$status}'", $statuses));

            DB::statement('ALTER TABLE supply_requests DROP CONSTRAINT IF EXISTS supply_requests_status_check');
            DB::statement("ALTER TABLE supply_requests ADD CONSTRAINT supply_requests_status_check CHECK (status IN ({$list}))");

            return;
        }

        Schema::table('supply_requests', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('Diajukan')->change();
        });
    }
};
