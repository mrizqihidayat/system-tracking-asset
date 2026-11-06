<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aset kembali dua tingkat: kategori → jenis (SPEC §4). Setiap jenis wajib punya kategori;
     * jenis yang sudah ada masuk kategori "Lainnya" agar staf memindahkannya lewat Data Master.
     */
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE public.categories ENABLE ROW LEVEL SECURITY');
        }

        Schema::table('asset_types', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->index('category_id');
        });

        if (DB::table('asset_types')->exists()) {
            $other = DB::table('categories')->insertGetId(['name' => 'Lainnya', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('asset_types')->update(['category_id' => $other]);
        }

        Schema::table('asset_types', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable(false)->change();
        });
    }

    public function down(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('categories');
    }
};
