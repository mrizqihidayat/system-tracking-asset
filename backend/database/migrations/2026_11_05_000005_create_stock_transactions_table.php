<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Setiap perubahan stok dicatat sebagai transaksi. Jumlah barang yang sudah ada menjadi saldo awal
     * agar consumables.quantity sama dengan hasil transaksi sejak awal.
     */
    public function up(): void
    {
        Schema::create('stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('consumable_id')->constrained('consumables')->cascadeOnDelete();
            $table->enum('type', ['Barang Masuk', 'Pemakaian', 'Koreksi', 'Penghapusan']);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->date('transaction_date');
            $table->string('reason', 20)->nullable();
            $table->text('note')->nullable();
            $table->boolean('is_opening')->default(false);
            $table->foreignId('room_id')->nullable()->constrained('rooms')->restrictOnDelete();
            $table->foreignId('supply_request_id')->nullable()->constrained('supply_requests')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['consumable_id', 'id']);
            $table->index('room_id');
            $table->index('supply_request_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE stock_transactions ADD CONSTRAINT stock_transactions_quantity_check CHECK (quantity <> 0)');
            DB::statement('ALTER TABLE stock_transactions ADD CONSTRAINT stock_transactions_balance_check CHECK (balance_after >= 0)');
        }

        DB::table('consumables')->where('quantity', '>', 0)->orderBy('id')->each(function ($consumable) {
            DB::table('stock_transactions')->insert([
                'consumable_id' => $consumable->id,
                'type' => 'Barang Masuk',
                'quantity' => $consumable->quantity,
                'balance_after' => $consumable->quantity,
                'transaction_date' => today()->toDateString(),
                'note' => 'Saldo awal',
                'is_opening' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transactions');
    }
};
