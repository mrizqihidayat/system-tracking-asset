<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Cara permintaan menjadi teks bebas (saran dari nilai yang ada); CHECK enum dihapus.
     */
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE request_catalog DROP CONSTRAINT IF EXISTS request_catalog_request_method_check');
        }

        Schema::table('request_catalog', function (Blueprint $table) {
            $table->string('request_method', 50)->change();
        });
    }

    public function down(): void
    {
        DB::table('request_catalog')
            ->whereNotIn('request_method', ['Memo', 'Form', 'Email', 'Lainnya'])
            ->update(['request_method' => 'Lainnya']);

        Schema::table('request_catalog', function (Blueprint $table) {
            $table->enum('request_method', ['Memo', 'Form', 'Email', 'Lainnya'])->change();
        });
    }
};
