<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Akun pegawai SHD dari API pegawai (login NIK) dan akun bersama manual (login username), T33.
     * Akun lama menjadi akun manual. Nonaktifkan akun = soft delete.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nik', 30)->nullable()->unique()->after('id');
            $table->string('username', 50)->nullable()->change();
            $table->string('department', 100)->nullable()->after('username');
            $table->string('source', 10)->default('manual')->after('department');
            $table->boolean('must_change_password')->default(false)->after('password');
            $table->softDeletes();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_source_check CHECK (source IN ('api', 'manual'))");
            // Akun api login dengan NIK, akun manual dengan username.
            DB::statement("ALTER TABLE users ADD CONSTRAINT users_login_id_check CHECK ((source = 'api' AND nik IS NOT NULL) OR (source = 'manual' AND username IS NOT NULL))");
        }
    }

    public function down(): void
    {
        if (DB::table('users')->whereNull('username')->exists()) {
            throw new RuntimeException('Tidak bisa rollback: sudah ada akun pegawai tanpa username.');
        }

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_source_check');
            DB::statement('ALTER TABLE users DROP CONSTRAINT IF EXISTS users_login_id_check');
        }

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['nik']);
            $table->dropColumn(['nik', 'department', 'source', 'must_change_password', 'deleted_at']);
            $table->string('username', 50)->nullable(false)->change();
        });
    }
};
