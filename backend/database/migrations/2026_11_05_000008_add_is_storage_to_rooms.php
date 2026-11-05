<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tanda gudang pada ruangan. Ruangan yang sudah menjadi lokasi simpan stok otomatis bertanda gudang.
     */
    public function up(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->boolean('is_storage')->default(false);
        });

        DB::table('rooms')
            ->whereIn('id', DB::table('consumables')->whereNotNull('room_id')->select('room_id'))
            ->update(['is_storage' => true]);
    }

    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('is_storage');
        });
    }
};
