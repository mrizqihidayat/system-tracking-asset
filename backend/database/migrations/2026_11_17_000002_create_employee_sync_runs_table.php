<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T34: riwayat sinkronisasi pegawai (`employees:sync`); baris terbaru tampil di Data Master > Departemen.
     * status: berhasil | gagal | dibatalkan (API mengembalikan daftar kosong).
     */
    public function up(): void
    {
        Schema::create('employee_sync_runs', function (Blueprint $table) {
            $table->id();
            $table->enum('status', ['berhasil', 'gagal', 'dibatalkan']);
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->unsignedInteger('total')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('deactivated')->default(0);
            $table->unsignedInteger('skipped')->default(0);
            $table->string('message', 255)->nullable();
            $table->timestamps();

            $table->index('started_at');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employee_sync_runs ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_sync_runs');
    }
};
