<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE_STATUSES = ['Dipinjam', 'Dikembalikan', 'Dibatalkan'];

    /**
     * Status transaksi "Hilang" (hilang saat dipinjam) + catatan & dokumen konfirmasi kerusakan.
     */
    public function up(): void
    {
        $this->setStatuses([...self::BASE_STATUSES, 'Hilang']);

        Schema::table('loans', function (Blueprint $table) {
            $table->text('damage_confirmation_note')->nullable()->after('damage_confirmed_by');
            $table->string('damage_confirmation_document', 100)->nullable()->after('damage_confirmation_note');
        });
    }

    public function down(): void
    {
        if (DB::table('loans')->where('status', 'Hilang')->exists()) {
            throw new RuntimeException('Tidak bisa rollback: sudah ada transaksi berstatus "Hilang".');
        }

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn(['damage_confirmation_note', 'damage_confirmation_document']);
        });

        $this->setStatuses(self::BASE_STATUSES);
    }

    /**
     * @param  list<string>  $statuses
     */
    private function setStatuses(array $statuses): void
    {
        if (DB::getDriverName() === 'pgsql') {
            $list = implode(', ', array_map(fn (string $status) => "'{$status}'", $statuses));

            DB::statement('ALTER TABLE loans DROP CONSTRAINT IF EXISTS loans_status_check');
            DB::statement("ALTER TABLE loans ADD CONSTRAINT loans_status_check CHECK (status IN ({$list}))");

            return;
        }

        Schema::table('loans', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->default('Dipinjam')->change();
        });
    }
};
