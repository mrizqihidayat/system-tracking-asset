<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consumables', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            $table->string('category', 100)->nullable();
            $table->string('unit', 20);
            $table->integer('quantity')->default(0);
            $table->integer('min_quantity')->default(0);
            $table->foreignId('provider_department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('catalog_id')->nullable()->constrained('request_catalog')->restrictOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->text('note')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->index('provider_department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('consumables');
    }
};
