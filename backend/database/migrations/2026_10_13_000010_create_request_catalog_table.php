<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('request_catalog', function (Blueprint $table) {
            $table->id();
            $table->string('item_name', 150);
            $table->foreignId('provider_department_id')->constrained('departments')->restrictOnDelete();
            $table->enum('request_method', ['Memo', 'Form', 'Email', 'Lainnya']);
            $table->boolean('must_return')->default(false);
            $table->integer('lead_time_days')->default(0);
            $table->text('note')->nullable();
            $table->timestamps();

            $table->index('provider_department_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('request_catalog');
    }
};
