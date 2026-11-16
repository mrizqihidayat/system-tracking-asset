<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T36: foto kondisi saat Terima kembali disimpan per peminjaman. File tertaut ke peminjaman lewat loan_id
     * (null = file biasa di aset). File tertaut tidak menggantikan slot Foto Kondisi, jadi riwayatnya utuh.
     */
    public function up(): void
    {
        Schema::table('asset_files', function (Blueprint $table) {
            $table->foreignId('loan_id')->nullable()->after('asset_id')->constrained('loans')->cascadeOnDelete();
            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::table('asset_files', function (Blueprint $table) {
            $table->dropConstrainedForeignId('loan_id');
        });
    }
};
