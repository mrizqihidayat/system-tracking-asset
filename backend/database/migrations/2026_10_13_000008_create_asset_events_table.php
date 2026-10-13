<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asset_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->enum('event_type', [
                'Registrasi', 'Dipinjam', 'Dikembalikan', 'Dibatalkan', 'Pindah Lokasi',
                'Lapor Rusak', 'Lapor Hilang', 'Servis', 'Ubah Data', 'Dihapus',
            ]);
            $table->timestamp('event_date');
            $table->string('party', 150)->nullable();
            $table->enum('condition', ['Baik', 'Rusak Ringan', 'Rusak Berat'])->nullable();
            $table->foreignId('from_room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->foreignId('to_room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->foreignId('loan_id')->nullable()->constrained('loans')->restrictOnDelete();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->index(['asset_id', 'event_date']);
            $table->index('loan_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_events');
    }
};
