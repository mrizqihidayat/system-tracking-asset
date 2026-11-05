<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Aset hanya punya satu tingkat: jenis aset. Kategori stok (consumable_categories) tidak terpengaruh.
     */
    public function up(): void
    {
        Schema::table('asset_types', function (Blueprint $table) {
            $table->dropForeign(['category_id']);
            $table->dropIndex(['category_id']);
            $table->dropColumn('category_id');
        });

        Schema::dropIfExists('categories');
    }

    /**
     * Struktur saja: semua jenis masuk satu kategori "Umum" karena pengelompokan lama sudah hilang.
     */
    public function down(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->timestamps();
        });

        $general = DB::table('categories')->insertGetId(['name' => 'Umum', 'created_at' => now(), 'updated_at' => now()]);

        Schema::table('asset_types', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->constrained('categories')->restrictOnDelete();
            $table->index('category_id');
        });

        DB::table('asset_types')->update(['category_id' => $general]);
    }
};
