<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T33 R2: akun pegawai SHD tidak lagi wajib ganti password; password awal = NIK dan tersimpan sebagai hash.
     * Akun api yang masih berstatus wajib ganti (password tersimpan = hash acak) diisi password = NIK
     * agar tetap bisa login, lalu kolom must_change_password dihapus.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('source', 'api')
            ->where('must_change_password', true)
            ->whereNotNull('nik')
            ->orderBy('id')
            ->each(function (object $user): void {
                DB::table('users')->where('id', $user->id)->update(['password' => Hash::make($user->nik)]);
            });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });
    }
};
