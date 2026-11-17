<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T40: pengaturan aplikasi (tampilan, konten, keamanan) yang bisa diubah akun pegawai SHD tanpa deploy ulang.
     * `settings` menyimpan nilai terkini per key; `setting_changes` mencatat setiap perubahan (siapa, apa, kapan).
     */
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->json('value')->nullable();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::create('setting_changes', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50);
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at');

            $table->index(['key', 'created_at']);
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE settings ENABLE ROW LEVEL SECURITY');
            DB::statement('ALTER TABLE setting_changes ENABLE ROW LEVEL SECURITY');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('setting_changes');
        Schema::dropIfExists('settings');
    }
};
