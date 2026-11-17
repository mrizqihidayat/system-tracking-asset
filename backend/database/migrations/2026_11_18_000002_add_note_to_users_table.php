<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T41 revisi: keterangan akun eksternal (mis. "Akun untuk Departemen SGA"), opsional. Akun lama kosong.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('note', 150)->nullable()->after('department');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('note');
        });
    }
};
