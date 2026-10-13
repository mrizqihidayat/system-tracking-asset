<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('building_id')->constrained('buildings')->restrictOnDelete();
            $table->string('name', 100);
            $table->timestamp('last_checked_at')->nullable();
            $table->foreignId('last_checked_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['building_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
