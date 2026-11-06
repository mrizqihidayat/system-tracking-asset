<?php

namespace Tests\Feature;

use App\Enums\CountMethod;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\StockTransaction;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stok berbasis transaksi (SPEC §10): kartu stok, form transaksi, hapus hanya untuk salah input, diterima sebagian.
 */
class StockTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Department $sga;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->user);
        $this->sga = Department::factory()->create(['name' => 'SGA']);
    }

    private function createItem(int $quantity = 10, array $overrides = []): Consumable
    {
        $id = $this->postJson('/api/consumables', [
            'name' => 'Pulpen', 'unit' => 'pcs', 'quantity' => $quantity, 'min_quantity' => 5, 'provider_department_id' => $this->sga->id,
            'room_id' => Room::factory()->create(['is_storage' => true])->id, ...$overrides,
        ])->assertCreated()->json('data.id');

        return Consumable::query()->findOrFail($id);
    }

    private function transact(Consumable $item, array $data)
    {
        return $this->postJson("/api/consumables/{$item->id}/transactions", $data);
    }

    private function assertBalanced(Consumable $item): void
    {
        $this->assertSame((int) $item->transactions()->sum('quantity'), $item->fresh()->quantity);
    }

    public function test_initial_quantity_becomes_opening_transaction(): void
    {
        $item = $this->createItem(10);

        $this->getJson("/api/consumables/{$item->id}/transactions")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.type', 'Barang Masuk')
            ->assertJsonPath('data.0.quantity', 10)
            ->assertJsonPath('data.0.balance_after', 10)
            ->assertJsonPath('data.0.note', 'Saldo awal')
            ->assertJsonPath('data.0.is_opening', true)
            ->assertJsonPath('data.0.created_by', 'Staf SHD')
            ->assertJsonPath('data.0.transaction_date', today()->toDateString());

        $empty = $this->createItem(0, ['name' => 'Tinta']);
        $this->assertSame(0, $empty->transactions()->count());
    }

    public function test_each_type_changes_stock_and_stays_balanced(): void
    {
        $item = $this->createItem(10);
        $room = Room::factory()->create(['name' => 'Ruang Kelas 1']);

        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => 5, 'note' => 'Beli sendiri'])
            ->assertCreated()
            ->assertJsonPath('data.quantity', 5)
            ->assertJsonPath('data.balance_after', 15)
            ->assertJsonPath('meta.consumable.quantity', 15);

        $this->transact($item, ['type' => 'Pemakaian', 'quantity' => 3, 'room_id' => $room->id])
            ->assertCreated()
            ->assertJsonPath('data.quantity', -3)
            ->assertJsonPath('data.room.name', 'Ruang Kelas 1')
            ->assertJsonPath('data.balance_after', 12);

        // Pemakaian ke ruangan hanya mencatat tujuan: Ada ruangan tidak berubah; ruangan boleh kosong.
        $this->transact($item, ['type' => 'Pemakaian', 'quantity' => 1])->assertCreated()->assertJsonPath('data.room', null);

        $this->transact($item, ['type' => 'Koreksi', 'quantity' => -2, 'note' => 'Hitung ulang'])->assertCreated()->assertJsonPath('data.balance_after', 9);
        $this->transact($item, ['type' => 'Koreksi', 'quantity' => 1, 'note' => 'Salah hitung'])->assertCreated()->assertJsonPath('data.balance_after', 10);

        $this->transact($item, ['type' => 'Penghapusan', 'quantity' => 4, 'reason' => 'rusak'])
            ->assertCreated()
            ->assertJsonPath('data.quantity', -4)
            ->assertJsonPath('data.reason', 'rusak')
            ->assertJsonPath('data.balance_after', 6);

        $this->assertSame(6, $item->fresh()->quantity);
        $this->assertBalanced($item);

        // Kartu stok: terbaru lebih dulu.
        $this->getJson("/api/consumables/{$item->id}/transactions")->assertOk()->assertJsonCount(7, 'data')->assertJsonPath('data.0.type', 'Penghapusan');
    }

    public function test_transaction_validation(): void
    {
        $item = $this->createItem(5);
        $room = Room::factory()->create();

        $this->transact($item, [])->assertJsonValidationErrors(['type', 'quantity']);
        $this->transact($item, ['type' => 'Lainnya', 'quantity' => 1])->assertJsonValidationErrors('type');
        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => 0])->assertJsonValidationErrors('quantity');
        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => -1])->assertJsonValidationErrors('quantity');
        $this->transact($item, ['type' => 'Pemakaian', 'quantity' => -1])->assertJsonValidationErrors('quantity');
        $this->transact($item, ['type' => 'Koreksi', 'quantity' => 0, 'note' => 'x'])->assertJsonValidationErrors(['quantity' => 'Jumlah tidak boleh 0.']);
        $this->transact($item, ['type' => 'Koreksi', 'quantity' => 1])->assertJsonValidationErrors(['note' => 'Keterangan wajib diisi.']);
        $this->transact($item, ['type' => 'Penghapusan', 'quantity' => 1])->assertJsonValidationErrors(['reason' => 'Pilih alasan penghapusan.']);
        $this->transact($item, ['type' => 'Penghapusan', 'quantity' => 1, 'reason' => 'hilang'])->assertJsonValidationErrors('reason');
        $this->transact($item, ['type' => 'Penghapusan', 'quantity' => 1, 'reason' => 'lainnya'])->assertJsonValidationErrors(['note' => 'Keterangan wajib diisi.']);
        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => 1, 'reason' => 'rusak'])->assertJsonValidationErrors('reason');
        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => 1, 'room_id' => $room->id])->assertJsonValidationErrors(['room_id' => 'Ruangan hanya untuk Pemakaian.']);
        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => 2147483648])->assertJsonValidationErrors('quantity');
        $this->transact($item, ['type' => 'Koreksi', 'quantity' => -2147483648, 'note' => 'x'])->assertJsonValidationErrors('quantity');

        $this->assertSame(1, $item->transactions()->count());
        $this->assertSame(5, $item->fresh()->quantity);
    }

    public function test_stock_never_goes_negative_or_over_column_limit(): void
    {
        $item = $this->createItem(2);

        $this->transact($item, ['type' => 'Pemakaian', 'quantity' => 3])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Stok tidak cukup (tersisa 2).']);
        $this->transact($item, ['type' => 'Koreksi', 'quantity' => -3, 'note' => 'x'])->assertUnprocessable();
        $this->transact($item, ['type' => 'Penghapusan', 'quantity' => 3, 'reason' => 'rusak'])->assertUnprocessable();

        $this->transact($item, ['type' => 'Barang Masuk', 'quantity' => 2147483647])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['quantity' => 'Jumlah stok melebihi batas.']);

        $this->assertSame(2, $item->fresh()->quantity);
        $this->assertSame(1, $item->transactions()->count());
    }

    public function test_old_quantity_endpoint_is_gone(): void
    {
        $item = $this->createItem();

        $this->patchJson("/api/consumables/{$item->id}/quantity", ['quantity' => 3])->assertStatus(404);
        $this->putJson("/api/consumables/{$item->id}", ['quantity' => 3])
            ->assertJsonValidationErrors(['quantity' => 'Jumlah diubah lewat transaksi stok.']);
    }

    public function test_delete_only_when_transactions_are_opening_balance_only(): void
    {
        $mistake = $this->createItem(10);
        $used = $this->createItem(10, ['name' => 'Tinta']);
        $room = Room::factory()->create();
        $standard = RoomStandard::factory()->create(['room_id' => $room->id, 'consumable_id' => $mistake->id]);
        $this->transact($used, ['type' => 'Penghapusan', 'quantity' => 10, 'reason' => 'kedaluwarsa'])->assertCreated();

        $this->getJson("/api/consumables/{$mistake->id}/usage")->assertJsonPath('data.delete_block_reason', null);
        $message = 'Barang sudah punya riwayat transaksi. Catat Penghapusan agar stok menjadi 0.';
        $this->getJson("/api/consumables/{$used->id}/usage")->assertJsonPath('data.delete_block_reason', $message);

        $this->deleteJson("/api/consumables/{$used->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id' => $message]);
        $this->assertModelExists($used);
        $this->assertSame(0, $used->fresh()->quantity);

        $this->deleteJson("/api/consumables/{$mistake->id}")->assertOk();
        $this->assertModelMissing($mistake);
        $this->assertModelMissing($standard);
        $this->assertSame(0, StockTransaction::query()->where('consumable_id', $mistake->id)->count());
    }

    public function test_fill_from_stock_records_usage_with_room(): void
    {
        $item = $this->createItem(3);
        $room = Room::factory()->create(['name' => 'Ruang Kelas 1']);
        $standard = RoomStandard::factory()->create([
            'room_id' => $room->id, 'consumable_id' => $item->id, 'standard_quantity' => 5, 'actual_quantity' => 0, 'count_method' => CountMethod::Manual,
        ]);

        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock")->assertOk()->assertJsonPath('filled', 3);

        $this->getJson("/api/consumables/{$item->id}/transactions")
            ->assertJsonPath('data.0.type', 'Pemakaian')
            ->assertJsonPath('data.0.quantity', -3)
            ->assertJsonPath('data.0.room.id', $room->id)
            ->assertJsonPath('data.0.room.building', $room->building->name);
        $this->assertBalanced($item);
    }

    public function test_fill_from_stock_accepts_custom_quantity_up_to_stock_with_note(): void
    {
        $item = $this->createItem(10);
        $room = Room::factory()->create();
        $standard = RoomStandard::factory()->create([
            'room_id' => $room->id, 'consumable_id' => $item->id, 'standard_quantity' => 5, 'actual_quantity' => 2, 'count_method' => CountMethod::Manual,
        ]);
        $url = "/api/room-standards/{$standard->id}/fill-from-stock";

        // Melebihi sisa stok ditolak, stok & Ada tidak berubah.
        $this->postJson($url, ['quantity' => 11])->assertJsonValidationErrors(['quantity' => 'Stok tidak cukup (tersisa 10).']);
        $this->postJson($url, ['quantity' => 0])->assertJsonValidationErrors('quantity');
        $this->assertSame(10, $item->fresh()->quantity);
        $this->assertSame(2, $standard->fresh()->actual_quantity);

        // Lebih kecil dari kekurangan: sisa kekurangan tetap tercatat.
        $this->postJson($url, ['quantity' => 1, 'note' => 'pelatihan K3'])
            ->assertOk()
            ->assertJsonPath('filled', 1)
            ->assertJsonPath('remaining_shortage', 2)
            ->assertJsonPath('stock_remaining', 9);

        // Melebihi kekurangan (masih ≤ stok) diterima; Ada boleh di atas standar.
        $this->postJson($url, ['quantity' => 6])
            ->assertOk()
            ->assertJsonPath('filled', 6)
            ->assertJsonPath('remaining_shortage', 0)
            ->assertJsonPath('data.result', 'Sesuai');
        $this->assertSame(9, $standard->fresh()->actual_quantity);
        $this->assertSame(3, $item->fresh()->quantity);

        $this->getJson("/api/consumables/{$item->id}/transactions")
            ->assertJsonPath('data.0.quantity', -6)
            ->assertJsonPath('data.0.note', 'Isi dari stok')
            ->assertJsonPath('data.1.quantity', -1)
            ->assertJsonPath('data.1.note', 'Isi dari stok · pelatihan K3')
            ->assertJsonPath('data.1.room.id', $room->id);
        $this->assertBalanced($item);

        // Sudah sesuai standar: tidak bisa diisi lagi dari sini.
        $this->postJson($url, ['quantity' => 1])->assertJsonValidationErrors(['standard' => 'Jumlah sudah sesuai standar.']);
    }

    private function stockRequest(Consumable $item, int $quantity = 10, array $overrides = []): SupplyRequest
    {
        return SupplyRequest::query()->findOrFail($this->postJson('/api/supply-requests', [
            'consumable_id' => $item->id, 'item_name' => $item->name, 'provider_department_id' => $this->sga->id,
            'quantity' => $quantity, 'request_method' => 'Memo', ...$overrides,
        ])->assertCreated()->json('data.id'));
    }

    private function receive(SupplyRequest $request, ?int $quantity = null)
    {
        return $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima', 'received_quantity' => $quantity]);
    }

    public function test_partial_receipt_until_full(): void
    {
        $item = $this->createItem(1);
        $request = $this->stockRequest($item);

        $this->getJson("/api/supply-requests/{$request->id}/status-impact?status=Diterima&received_quantity=4")
            ->assertExactJson(['data' => ['message' => 'Stok Pulpen akan bertambah 4.', 'clamped' => false]]);

        $this->receive($request, 4)
            ->assertOk()
            ->assertJsonPath('data.status', 'Diterima sebagian')
            ->assertJsonPath('data.received_quantity', 4);
        $this->assertSame(5, $item->fresh()->quantity);

        $this->getJson("/api/consumables/{$item->id}/transactions")
            ->assertJsonPath('data.0.type', 'Barang Masuk')
            ->assertJsonPath('data.0.quantity', 4)
            ->assertJsonPath('data.0.supply_request_id', $request->id);

        // Masih terbuka: tautan di stok tetap ke permintaan ini.
        $this->getJson("/api/consumables/{$item->id}")->assertJsonPath('data.open_request.id', $request->id);

        $this->receive($request, 7)
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['received_quantity' => 'Jumlah diterima melebihi sisa permintaan (6).']);

        $this->receive($request, 2)->assertOk()->assertJsonPath('data.status', 'Diterima sebagian')->assertJsonPath('data.received_quantity', 6);

        // Tanpa jumlah = semua sisa.
        $this->receive($request)->assertOk()->assertJsonPath('data.status', 'Diterima')->assertJsonPath('data.received_quantity', 10);
        $this->assertSame(11, $item->fresh()->quantity);
        $this->assertBalanced($item);

        $this->receive($request, 1)->assertJsonValidationErrors('status');
    }

    public function test_partial_status_cannot_be_chosen_directly_and_quantity_only_for_received(): void
    {
        $request = $this->stockRequest($this->createItem(1));

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima sebagian'])->assertJsonValidationErrors('status');
        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dibatalkan', 'received_quantity' => 1])->assertJsonValidationErrors('received_quantity');
        $this->receive($request, 0)->assertJsonValidationErrors('received_quantity');
    }

    public function test_cancelling_partial_receipt_closes_remainder_and_keeps_stock(): void
    {
        $item = $this->createItem(1);
        $request = $this->stockRequest($item);
        $this->receive($request, 3)->assertOk();

        $this->getJson("/api/supply-requests/{$request->id}/status-impact?status=Dibatalkan")
            ->assertJsonPath('data.message', 'Sisa permintaan dibatalkan. Stok yang sudah masuk (3) tetap.');

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dibatalkan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dibatalkan')
            ->assertJsonPath('data.received_quantity', 3);

        $this->assertSame(4, $item->fresh()->quantity);
        $this->assertBalanced($item);
    }

    public function test_cancelling_full_receipt_reverses_with_correction_and_stops_at_zero(): void
    {
        $item = $this->createItem(0);
        $request = $this->stockRequest($item, 5);
        $this->receive($request)->assertOk();
        $this->transact($item, ['type' => 'Pemakaian', 'quantity' => 2])->assertCreated();

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dibatalkan'])
            ->assertOk()
            ->assertJsonPath('meta.impact.clamped', true)
            ->assertJsonPath('data.received_quantity', 0);

        $this->assertSame(0, $item->fresh()->quantity);
        $this->assertBalanced($item);

        $this->getJson("/api/consumables/{$item->id}/transactions")
            ->assertJsonPath('data.0.type', 'Koreksi')
            ->assertJsonPath('data.0.quantity', -3)
            ->assertJsonPath('data.0.note', "Permintaan #{$request->id} Dibatalkan")
            ->assertJsonPath('data.0.supply_request_id', $request->id);
    }

    public function test_returning_full_receipt_reduces_stock(): void
    {
        $item = $this->createItem(0);
        $request = $this->stockRequest($item, 5, ['must_return' => true]);
        $this->receive($request)->assertOk();

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Dikembalikan'])
            ->assertOk()
            ->assertJsonPath('data.status', 'Dikembalikan')
            ->assertJsonPath('data.received_quantity', 5);

        $this->assertSame(0, $item->fresh()->quantity);
        $this->assertBalanced($item);
    }

    public function test_request_without_stock_link_is_received_at_once(): void
    {
        $request = SupplyRequest::factory()->create(['provider_department_id' => $this->sga->id, 'quantity' => 5]);

        $this->patchJson("/api/supply-requests/{$request->id}/status", ['status' => 'Diterima', 'received_quantity' => 2])
            ->assertOk()
            ->assertJsonPath('data.status', 'Diterima')
            ->assertJsonPath('meta.impact.message', 'Tidak mengubah stok.');

        $this->assertSame(0, StockTransaction::query()->count());
    }

    public function test_room_with_stock_history_cannot_be_deleted(): void
    {
        $item = $this->createItem(5);
        $room = Room::factory()->create();
        $this->transact($item, ['type' => 'Pemakaian', 'quantity' => 1, 'room_id' => $room->id])->assertCreated();

        $this->getJson("/api/rooms/{$room->id}/usage")->assertJsonPath('data.items', [['label' => 'riwayat stok', 'count' => 1]]);
        $this->deleteJson("/api/rooms/{$room->id}")->assertUnprocessable();
    }

    public function test_migration_turns_existing_quantity_into_opening_balance(): void
    {
        $migration = require database_path('migrations/2026_11_05_000005_create_stock_transactions_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('stock_transactions'));

        DB::table('consumables')->insert([
            ['name' => 'Ada', 'unit' => 'pcs', 'quantity' => 7, 'min_quantity' => 0, 'provider_department_id' => $this->sga->id],
            ['name' => 'Kosong', 'unit' => 'pcs', 'quantity' => 0, 'min_quantity' => 0, 'provider_department_id' => $this->sga->id],
        ]);

        $migration->up();

        $row = StockTransaction::query()->sole();
        $this->assertSame(7, $row->quantity);
        $this->assertSame(7, $row->balance_after);
        $this->assertTrue($row->is_opening);
        $this->assertSame('Ada', $row->consumable->name);
    }
}
