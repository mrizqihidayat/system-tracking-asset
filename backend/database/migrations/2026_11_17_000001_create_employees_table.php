<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T34: salinan lokal data pegawai dari API pegawai (diisi `employees:sync`). Hanya kolom yang dibutuhkan;
     * data pribadi lain (tanggal lahir, agama, jenis kelamin, telepon, tanggal MPP/pensiun) tidak disimpan.
     * Pegawai yang hilang dari API dinonaktifkan (is_active), tidak dihapus.
     */
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('nik', 30)->unique();
            $table->string('name', 150);
            $table->string('group_name', 100)->nullable();
            $table->string('division', 100)->nullable();
            $table->string('department_code', 100)->nullable();
            $table->string('section', 100)->nullable();
            $table->string('position', 150)->nullable();
            $table->string('email', 150)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('department_code');
            $table->index('name');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE employees ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
