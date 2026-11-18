<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T44: asal sinkronisasi pegawai. api = backend menarik sendiri dari EMPLOYEE_API_URL (`employees:sync`);
     * agen = dikirim agen sinkron di server perusahaan (POST /api/internal/employees/push). Run lama = api.
     */
    public function up(): void
    {
        Schema::table('employee_sync_runs', function (Blueprint $table) {
            $table->string('source', 10)->default('api');
        });
    }

    public function down(): void
    {
        Schema::table('employee_sync_runs', function (Blueprint $table) {
            $table->dropColumn('source');
        });
    }
};
