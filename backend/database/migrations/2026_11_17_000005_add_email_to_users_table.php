<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T40: email akun (tujuan OTP lupa password). Selalu disimpan huruf kecil sehingga unik tanpa beda huruf
     * besar/kecil. Akun tanpa email wajib mengisinya setelah login, kecuali `email_exempt` (akun bersama `staf1`).
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('email', 150)->nullable()->unique()->after('department');
            $table->boolean('email_exempt')->default(false)->after('email');
        });

        DB::table('users')->where('source', 'manual')->where('username', 'staf1')->update(['email_exempt' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->dropColumn(['email', 'email_exempt']);
        });
    }
};
