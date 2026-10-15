<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Kode lama aset (saat jenis diganti), agar tetap bisa dicari dan tidak dipakai jenis lain.
     */
    public function up(): void
    {
        Schema::create('asset_code_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->foreignId('asset_type_id')->constrained('asset_types')->restrictOnDelete();
            $table->string('code', 20)->unique();
            $table->timestamp('replaced_at');
            $table->foreignId('replaced_by')->constrained('users')->restrictOnDelete();

            $table->index('asset_id');
            $table->index('asset_type_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.asset_code_histories ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_code_histories');
    }
};
