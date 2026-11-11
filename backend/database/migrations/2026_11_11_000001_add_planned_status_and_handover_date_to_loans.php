<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const BASE_STATUSES = ['Dipinjam', 'Dikembalikan', 'Dibatalkan', 'Hilang'];

    /**
     * Pemesanan peminjaman (T31): status "Direncanakan" (tanggal pinjam di masa depan, aset tetap Tersedia)
     * dan tanggal penyerahan sebenarnya saat aksi Serahkan. borrow_date tetap tanggal rencana.
     */
    public function up(): void
    {
        $this->setStatuses([...self::BASE_STATUSES, 'Direncanakan']);

        Schema::table('loans', function (Blueprint $table) {
            $table->date('handover_date')->nullable()->after('borrow_date');
        });
    }

    public function down(): void
    {
        if (DB::table('loans')->where('status', 'Direncanakan')->exists()) {
            throw new RuntimeException('Tidak bisa rollback: sudah ada transaksi berstatus "Direncanakan".');
        }

        Schema::table('loans', function (Blueprint $table) {
            $table->dropColumn('handover_date');
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

        // SQLite membangun ulang tabel saat change() dan index unik parsial kehilangan klausa WHERE-nya,
        // sehingga beberapa transaksi Direncanakan/Dikembalikan pada satu aset ikut ditolak. Buat ulang.
        DB::statement('DROP INDEX IF EXISTS loans_asset_id_active_unique');
        DB::statement("CREATE UNIQUE INDEX loans_asset_id_active_unique ON loans (asset_id) WHERE status = 'Dipinjam'");
    }
};
