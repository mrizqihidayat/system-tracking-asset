<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();
            $table->foreignId('asset_type_id')->constrained('asset_types')->restrictOnDelete();
            $table->string('name');
            $table->string('brand', 100)->nullable();
            $table->string('serial_number', 100)->nullable();
            $table->text('specification')->nullable();
            $table->foreignId('source_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->string('pic_name', 150)->nullable();
            $table->string('finance_code', 100)->nullable();
            $table->date('acquisition_date')->nullable();
            $table->date('next_service_date')->nullable();
            $table->enum('condition', ['Baik', 'Rusak Ringan', 'Rusak Berat'])->default('Baik');
            $table->enum('status', ['Tersedia', 'Dipinjam', 'Perbaikan', 'Hilang'])->default('Tersedia');
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->softDeletes();
            $table->enum('deletion_reason', ['Tidak digunakan', 'Dijual', 'Lainnya'])->nullable();
            $table->text('deletion_note')->nullable();
            $table->foreignId('deleted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('asset_type_id');
            $table->index('source_department_id');
            $table->index('room_id');
            $table->index('status');
            $table->index('next_service_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assets');
    }
};
