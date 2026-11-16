<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tabel untuk CACHE_STORE=database (T38). Dibutuhkan di Vercel karena disk container tidak permanen, dan agar
 * throttle login serta kunci sinkronisasi pegawai (Cache::lock) tetap global antar instance.
 *
 * Bernama 0001_01_01_000001 agar jalan paling awal: migration lama 2026_11_09_044334 memanggil AppCache::flush()
 * dan gagal di database kosong bila tabel cache belum ada (migration lama tidak boleh diubah).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cache', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->mediumText('value');
            $table->bigInteger('expiration')->index();
        });

        Schema::create('cache_locks', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->string('owner');
            $table->bigInteger('expiration')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cache_locks');
        Schema::dropIfExists('cache');
    }
};
