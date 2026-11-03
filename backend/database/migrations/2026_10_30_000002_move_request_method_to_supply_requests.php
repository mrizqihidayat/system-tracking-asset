<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cara permintaan dicatat per permintaan (log jalur pengiriman), bukan default di katalog.
 * Permintaan lama tidak diisi (null); kolom katalog dihapus (keputusan pengguna, T26g).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->string('request_method', 50)->nullable()->after('provider_department_id');
        });

        Schema::table('request_catalog', function (Blueprint $table) {
            $table->dropColumn('request_method');
        });
    }

    public function down(): void
    {
        Schema::table('request_catalog', function (Blueprint $table) {
            $table->string('request_method', 50)->nullable();
        });

        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropColumn('request_method');
        });
    }
};
