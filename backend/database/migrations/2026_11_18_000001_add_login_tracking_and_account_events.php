<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T41: tab Pengaturan > Akun.
     * - `users.last_login_at`: diisi saat login berhasil (akun lama kosong sampai login berikutnya).
     * - `users.password_changed_at`: kosong = password masih bawaan (NIK). Akun pegawai yang sudah ada dicek sekali
     *   dengan Hash::check(nik, password); akun bersama memakai `created_at` (passwordnya ditentukan staf).
     * - `account_events`: riwayat aksi pada akun (siapa, kapan, aksi). Hanya ditambah, tidak diubah.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('last_login_at')->nullable()->after('must_change_password');
            $table->timestamp('password_changed_at')->nullable()->after('last_login_at');
        });

        DB::table('users')->orderBy('id')->each(function (object $user): void {
            $changedAt = $user->source === User::SOURCE_API
                ? (Hash::check((string) $user->nik, $user->password) ? null : now())
                : ($user->created_at ?? now());

            DB::table('users')->where('id', $user->id)->update(['password_changed_at' => $changedAt]);
        });

        Schema::create('account_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 30);
            $table->timestamp('created_at');

            $table->index(['user_id', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE account_events ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('account_events');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['last_login_at', 'password_changed_at']);
        });
    }
};
