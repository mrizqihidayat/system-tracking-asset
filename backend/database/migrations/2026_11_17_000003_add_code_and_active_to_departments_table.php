<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T34: departemen dibentuk dari API pegawai. `code` = nilai kolom departemen di API (kunci sinkronisasi),
     * `name` = nama lengkap yang bisa diubah admin (awalnya sama dengan kode). Departemen yang tidak ada lagi di
     * API ditandai is_active = false (tetap tersimpan, tidak muncul sebagai pilihan baru). Baris lama: code = name.
     */
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->string('code', 100)->nullable()->unique()->after('id');
            $table->boolean('is_active')->default(true)->after('is_internal');
        });

        DB::table('departments')->update(['code' => DB::raw('name')]);
    }

    public function down(): void
    {
        Schema::table('departments', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn(['code', 'is_active']);
        });
    }
};
