<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->foreignId('consumable_id')->nullable()->constrained('consumables')->nullOnDelete();
            $table->foreignId('room_standard_id')->nullable()->constrained('room_standards')->nullOnDelete();
            $table->string('applied_to', 10)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supply_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('consumable_id');
            $table->dropConstrainedForeignId('room_standard_id');
            $table->dropColumn('applied_to');
        });
    }
};
