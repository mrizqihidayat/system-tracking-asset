<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('supply_requests', function (Blueprint $table) {
            $table->id();
            $table->date('request_date');
            $table->foreignId('catalog_id')->nullable()->constrained('request_catalog')->restrictOnDelete();
            $table->string('item_name', 150);
            $table->integer('quantity');
            $table->string('purpose')->nullable();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->foreignId('provider_department_id')->constrained('departments')->restrictOnDelete();
            $table->enum('status', ['Diajukan', 'Diterima', 'Dikembalikan', 'Dibatalkan'])->default('Diajukan');
            $table->timestamp('received_at')->nullable();
            $table->timestamp('returned_at')->nullable();
            $table->text('note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index('status');
            $table->index('provider_department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supply_requests');
    }
};
