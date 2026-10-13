<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('room_standards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->string('item_name', 150);
            $table->foreignId('source_department_id')->constrained('departments')->restrictOnDelete();
            $table->integer('standard_quantity');
            $table->enum('count_method', ['QR', 'Manual']);
            $table->foreignId('asset_type_id')->nullable()->constrained('asset_types')->restrictOnDelete();
            $table->integer('actual_quantity')->nullable();
            $table->foreignId('consumable_id')->nullable()->constrained('consumables')->restrictOnDelete();
            $table->string('fulfillment_note')->nullable();
            $table->foreignId('catalog_id')->nullable()->constrained('request_catalog')->restrictOnDelete();
            $table->timestamps();

            $table->index('room_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('room_standards');
    }
};
