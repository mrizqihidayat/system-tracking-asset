<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->enum('file_type', ['Foto Aset', 'Foto Kondisi', 'BAST', 'Garansi', 'Lainnya']);
            $table->string('path', 500);
            $table->string('original_name');
            $table->string('mime_type', 100)->nullable();
            $table->integer('size')->nullable();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index('asset_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_files');
    }
};
