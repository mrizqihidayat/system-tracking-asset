<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T40: lupa password via OTP dan wajib ganti password setelah direset staf SHD.
     * - `users.must_change_password`: hanya menyala setelah reset/ubah password oleh staf SHD; login pertama
     *   dengan password = NIK tetap tidak wajib ganti (T33 R2).
     * - `password_otps`: satu baris per akun. OTP dan token reset disimpan sebagai hash, sekali pakai.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('must_change_password')->default(false)->after('password');
        });

        Schema::create('password_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('otp_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('last_sent_at');
            $table->timestamp('used_at')->nullable();
            $table->string('reset_token_hash', 64)->nullable();
            $table->timestamp('reset_expires_at')->nullable();
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE password_otps ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('password_otps');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('must_change_password');
        });
    }
};
