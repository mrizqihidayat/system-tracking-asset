<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * T30: barang habis pakai di ruangan berbasis transaksi. Riwayat dikunci per ruangan + barang.
     * "Belum dicek" pindah dari actual_quantity null ke checked_at null, sehingga Ada (saldo) selalu terisi.
     * Jumlah Ada yang sudah ada menjadi Saldo awal (ruangan bukan gudang) agar saldo sama dengan hasil transaksi.
     */
    public function up(): void
    {
        Schema::create('room_stock_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('rooms')->restrictOnDelete();
            $table->foreignId('consumable_id')->constrained('consumables')->cascadeOnDelete();
            $table->string('type', 20);
            $table->integer('quantity');
            $table->integer('balance_after');
            $table->date('transaction_date');
            $table->text('note')->nullable();
            $table->foreignId('stock_transaction_id')->nullable()->constrained('stock_transactions')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'consumable_id', 'id']);
            $table->index('stock_transaction_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE room_stock_transactions ADD CONSTRAINT room_stock_transactions_type_check CHECK (type IN ('Saldo awal', 'Diisi', 'Terpakai', 'Koreksi'))");
            DB::statement('ALTER TABLE room_stock_transactions ADD CONSTRAINT room_stock_transactions_quantity_check CHECK (quantity <> 0)');
            DB::statement('ALTER TABLE room_stock_transactions ADD CONSTRAINT room_stock_transactions_balance_check CHECK (balance_after >= 0)');
        }

        Schema::table('room_standards', function (Blueprint $table) {
            $table->timestamp('checked_at')->nullable()->after('actual_quantity');
        });

        // Sudah pernah dicek → checked_at dari waktu ubah terakhir; belum → Ada 0 (tetap Belum dicek).
        DB::table('room_standards')->where('count_method', 'Manual')->whereNotNull('actual_quantity')->update(['checked_at' => DB::raw('updated_at')]);
        DB::table('room_standards')->where('count_method', 'Manual')->whereNull('actual_quantity')->update(['actual_quantity' => 0]);

        DB::table('room_standards')
            ->join('rooms', 'rooms.id', '=', 'room_standards.room_id')
            ->where('room_standards.count_method', 'Manual')
            ->whereNotNull('room_standards.consumable_id')
            ->where('room_standards.actual_quantity', '>', 0)
            ->where('rooms.is_storage', false)
            ->orderBy('room_standards.id')
            ->select('room_standards.room_id', 'room_standards.consumable_id', 'room_standards.actual_quantity', 'room_standards.updated_at')
            ->each(function ($standard) {
                DB::table('room_stock_transactions')->insert([
                    'room_id' => $standard->room_id,
                    'consumable_id' => $standard->consumable_id,
                    'type' => 'Saldo awal',
                    'quantity' => $standard->actual_quantity,
                    'balance_after' => $standard->actual_quantity,
                    'transaction_date' => substr((string) ($standard->updated_at ?? now()), 0, 10),
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Belum dicek kembali ditandai actual_quantity null; riwayat transaksi ruangan hilang.
     */
    public function down(): void
    {
        DB::table('room_standards')->where('count_method', 'Manual')->whereNull('checked_at')->update(['actual_quantity' => null]);

        Schema::table('room_standards', function (Blueprint $table) {
            $table->dropColumn('checked_at');
        });

        Schema::dropIfExists('room_stock_transactions');
    }
};
