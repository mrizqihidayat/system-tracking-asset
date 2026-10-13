<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loans', function (Blueprint $table) {
            $table->id();
            $table->foreignId('asset_id')->constrained('assets')->restrictOnDelete();
            $table->string('borrower_name', 150);
            $table->string('borrower_unit', 100);
            $table->string('purpose');
            $table->foreignId('destination_room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->string('destination_note')->nullable();
            $table->date('borrow_date');
            $table->date('due_date');
            $table->enum('condition_out', ['Baik', 'Rusak Ringan', 'Rusak Berat']);
            $table->string('approval_number', 100)->nullable();
            $table->date('approval_date')->nullable();
            $table->foreignId('borrowed_by')->constrained('users')->restrictOnDelete();
            $table->enum('status', ['Dipinjam', 'Dikembalikan', 'Dibatalkan'])->default('Dipinjam');
            $table->timestamp('return_date')->nullable();
            $table->enum('return_condition', ['Baik', 'Rusak Ringan', 'Rusak Berat'])->nullable();
            $table->foreignId('return_room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->foreignId('received_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->enum('damage_confirmation', ['Menunggu Konfirmasi', 'Dikonfirmasi'])->nullable();
            $table->timestamp('damage_confirmed_at')->nullable();
            $table->foreignId('damage_confirmed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->text('cancel_reason')->nullable();
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('asset_id');
            $table->index('status');
            $table->index('due_date');
        });

        // Maksimal satu loan berstatus Dipinjam per aset.
        DB::statement("CREATE UNIQUE INDEX loans_asset_id_active_unique ON loans (asset_id) WHERE status = 'Dipinjam'");
    }

    public function down(): void
    {
        Schema::dropIfExists('loans');
    }
};
