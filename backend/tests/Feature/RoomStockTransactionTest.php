<?php

namespace Tests\Feature;

use App\Enums\CountMethod;
use App\Enums\RoomStockTransactionType;
use App\Enums\StockTransactionType;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Consumable;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\RoomStockTransaction;
use App\Models\StockTransaction;
use App\Models\User;
use App\Services\RoomReadinessService;
use App\Services\RoomStockService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use RuntimeException;
use Tests\TestCase;

/**
 * T30 (+ R1): barang habis pakai ruangan berbasis transaksi, dicatat per barang lewat popup "Catat perubahan".
 */
class RoomStockTransactionTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Room $room;

    private Consumable $tisu;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->user);
        $this->room = Room::factory()->create(['name' => 'Ruang Kelas 1']);
        $this->tisu = Consumable::factory()->create(['name' => 'Tisu', 'quantity' => 0]);
    }

    /**
     * Barang baru ditambahkan lewat API (Ada = saldo, Belum dicek).
     */
    private function addItem(?Consumable $consumable = null, int $standard = 5): RoomStandard
    {
        $id = $this->postJson("/api/rooms/{$this->room->id}/standards", ['consumable_id' => ($consumable ?? $this->tisu)->id, 'standard_quantity' => $standard])
            ->assertCreated()
            ->json('data.id');

        return RoomStandard::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $extra  reason, note
     */
    private function adjust(RoomStandard $standard, int $quantity, array $extra = []): TestResponse
    {
        return $this->postJson("/api/room-standards/{$standard->id}/adjust", ['quantity' => $quantity, ...$extra]);
    }

    /**
     * @return list<array{type: string, quantity: int, balance_after: int, note: ?string}>
     */
    private function ledger(?Consumable $consumable = null): array
    {
        return RoomStockTransaction::query()
            ->where('room_id', $this->room->id)
            ->where('consumable_id', ($consumable ?? $this->tisu)->id)
            ->orderBy('id')
            ->get()
            ->map(fn (RoomStockTransaction $t) => ['type' => $t->type->value, 'quantity' => $t->quantity, 'balance_after' => $t->balance_after, 'note' => $t->note])
            ->all();
    }

    private function assertBalanceMatchesLedger(RoomStandard $standard): void
    {
        $sum = (int) RoomStockTransaction::query()->where('room_id', $standard->room_id)->where('consumable_id', $standard->consumable_id)->sum('quantity');

        $this->assertSame($sum, $standard->fresh()->actual_quantity);
    }

    public function test_new_item_without_history_has_zero_balance_and_is_unchecked(): void
    {
        $standard = $this->addItem();

        $this->assertSame(0, $standard->actual_quantity);
        $this->assertNull($standard->checked_at);

        $row = $this->getJson("/api/rooms/{$this->room->id}/checklist")->assertOk()->json('data.consumables.0');
        $this->assertNull($row['present_quantity']);
        $this->assertSame(0, $row['recorded_quantity']);
        $this->assertSame('Belum dicek', $row['result']);
        $this->assertNull($row['shortage']);

        // Belum dicek tidak dihitung kurang dan tidak bisa Isi dari stok.
        $this->tisu->update(['quantity' => 10]);
        $this->getJson("/api/rooms/{$this->room->id}/checklist")->assertJsonPath('meta.summary.shortage_items', 0)->assertJsonPath('meta.summary.unchecked_items', 1);
        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock")->assertJsonValidationErrors(['standard' => 'Barang belum dicek. Catat perubahan atau tandai ruangan sudah dicek dulu.']);
    }

    public function test_first_adjust_records_opening_balance_and_checks_only_that_row(): void
    {
        $standard = $this->addItem();
        $other = $this->addItem(Consumable::factory()->create(['name' => 'Pulpen']));

        $this->adjust($standard, 4)
            ->assertOk()
            ->assertJsonPath('message', 'Saldo awal dicatat.')
            ->assertJsonPath('transaction.type', 'Saldo awal')
            ->assertJsonPath('data.present_quantity', 4)
            ->assertJsonPath('data.result', 'Kurang');

        $this->assertSame([['type' => 'Saldo awal', 'quantity' => 4, 'balance_after' => 4, 'note' => null]], $this->ledger());
        $this->assertNotNull($standard->fresh()->checked_at);
        $this->assertNull($other->fresh()->checked_at);
        // Status cek ruangan hanya dari Tandai sudah dicek.
        $this->assertNull($this->room->fresh()->last_checked_at);
    }

    public function test_decrease_uses_reason_chosen_by_staff(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 20)->assertOk();

        $this->adjust($standard, 17, ['reason' => 'Terpakai'])->assertOk();
        $this->adjust($standard, 15, ['reason' => 'Hilang', 'note' => 'Tidak ada di laci'])->assertOk();
        $this->adjust($standard, 14, ['reason' => 'Rusak'])->assertOk();
        $this->adjust($standard, 10, ['reason' => 'Kedaluwarsa'])->assertOk();

        $this->assertSame([
            ['type' => 'Saldo awal', 'quantity' => 20, 'balance_after' => 20, 'note' => null],
            ['type' => 'Terpakai', 'quantity' => -3, 'balance_after' => 17, 'note' => null],
            ['type' => 'Hilang', 'quantity' => -2, 'balance_after' => 15, 'note' => 'Tidak ada di laci'],
            ['type' => 'Rusak', 'quantity' => -1, 'balance_after' => 14, 'note' => null],
            ['type' => 'Kedaluwarsa', 'quantity' => -4, 'balance_after' => 10, 'note' => null],
        ], $this->ledger());
        $this->assertBalanceMatchesLedger($standard);
    }

    public function test_decrease_without_reason_or_with_invalid_reason_is_rejected(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 5)->assertOk();

        $this->adjust($standard, 3)->assertJsonValidationErrors(['reason' => 'Pilih alasan.']);
        $this->adjust($standard, 3, ['reason' => 'Koreksi'])->assertJsonValidationErrors(['reason' => 'Alasan tidak valid.']);

        $this->assertCount(1, $this->ledger());
        $this->assertSame(5, $standard->fresh()->actual_quantity);
    }

    public function test_increase_requires_note_and_records_correction(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 2)->assertOk();

        $this->adjust($standard, 5)->assertJsonValidationErrors(['note' => 'Keterangan wajib diisi untuk koreksi.']);
        $this->adjust($standard, 5, ['note' => '   '])->assertJsonValidationErrors('note');
        $this->adjust($standard, 5, ['note' => 'Ditemukan di laci', 'reason' => 'Rusak'])->assertOk()->assertJsonPath('transaction.type', 'Koreksi');

        $this->assertSame([
            ['type' => 'Saldo awal', 'quantity' => 2, 'balance_after' => 2, 'note' => null],
            ['type' => 'Koreksi', 'quantity' => 3, 'balance_after' => 5, 'note' => 'Ditemukan di laci'],
        ], $this->ledger());
        $this->assertBalanceMatchesLedger($standard);
    }

    public function test_same_quantity_is_rejected_for_checked_row(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 3)->assertOk();

        $this->adjust($standard, 3)->assertJsonValidationErrors(['quantity' => 'Jumlah sama dengan catatan.']);

        $this->assertCount(1, $this->ledger());
    }

    public function test_unchecked_row_with_same_quantity_is_confirmed_without_transaction(): void
    {
        // Barang baru dengan 0: Konfirmasi jumlah.
        $standard = $this->addItem();

        $this->adjust($standard, 0)
            ->assertOk()
            ->assertJsonPath('message', 'Jumlah dikonfirmasi.')
            ->assertJsonPath('transaction', null)
            ->assertJsonPath('data.present_quantity', 0)
            ->assertJsonPath('data.result', 'Kurang');

        $this->assertSame([], $this->ledger());
        $this->assertNotNull($standard->fresh()->checked_at);
        $this->assertNull($this->room->fresh()->last_checked_at);
    }

    public function test_mark_checked_checks_all_rows_without_transactions(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 4)->assertOk();
        $this->deleteJson("/api/room-standards/{$standard->id}")->assertOk();
        $again = $this->addItem();
        $pulpen = $this->addItem(Consumable::factory()->create(['name' => 'Pulpen']));

        $this->postJson("/api/rooms/{$this->room->id}/check")
            ->assertOk()
            ->assertJsonPath('meta.summary.unchecked_items', 0)
            ->assertJsonPath('meta.last_checked_by', 'Staf SHD');

        $this->assertNotNull($again->fresh()->checked_at);
        $this->assertNotNull($pulpen->fresh()->checked_at);
        $this->assertSame(4, $again->fresh()->actual_quantity);
        $this->assertCount(1, RoomStockTransaction::all());
        $this->assertNotNull($this->room->fresh()->last_checked_at);
    }

    public function test_mark_checked_is_allowed_for_room_with_assets_only(): void
    {
        $type = AssetType::factory()->create();
        Asset::factory()->create(['asset_type_id' => $type->id, 'room_id' => $this->room->id]);

        $this->postJson("/api/rooms/{$this->room->id}/check")->assertOk();

        $this->assertNotNull($this->room->fresh()->last_checked_at);
    }

    public function test_fill_from_stock_records_stock_and_room_transactions_together(): void
    {
        $this->tisu->update(['quantity' => 10]);
        $standard = $this->addItem();
        $this->adjust($standard, 1)->assertOk();

        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock", ['quantity' => 3, 'note' => 'Untuk rapat'])->assertOk();

        $stock = StockTransaction::query()->where('consumable_id', $this->tisu->id)->where('type', StockTransactionType::Pemakaian->value)->sole();
        $diisi = RoomStockTransaction::query()->where('type', RoomStockTransactionType::Diisi->value)->sole();

        $this->assertSame(-3, $stock->quantity);
        $this->assertSame(3, $diisi->quantity);
        $this->assertSame(4, $diisi->balance_after);
        $this->assertSame($stock->id, $diisi->stock_transaction_id);
        $this->assertSame('Isi dari stok · Untuk rapat', $diisi->note);
        $this->assertSame(7, $this->tisu->fresh()->quantity);
        $this->assertBalanceMatchesLedger($standard);
    }

    public function test_fill_from_stock_rolls_back_stock_when_room_transaction_fails(): void
    {
        $this->tisu->update(['quantity' => 10]);
        $standard = $this->addItem();
        $this->adjust($standard, 1)->assertOk();

        $this->partialMock(RoomStockService::class, fn ($mock) => $mock->shouldReceive('record')->andThrow(new RuntimeException('gagal')));

        try {
            app(RoomReadinessService::class)->fillFromStock($standard, $this->user, 3);
            $this->fail('Seharusnya gagal.');
        } catch (RuntimeException) {
            // Diharapkan.
        }

        $this->assertSame(10, $this->tisu->fresh()->quantity);
        $this->assertFalse(StockTransaction::query()->where('type', StockTransactionType::Pemakaian->value)->exists());
        $this->assertSame(1, $standard->fresh()->actual_quantity);
    }

    public function test_storage_room_does_not_use_room_ledger(): void
    {
        $storage = Room::factory()->create(['name' => 'Gudang', 'is_storage' => true]);
        $this->tisu->update(['quantity' => 10]);
        $standard = RoomStandard::factory()->create(['room_id' => $storage->id, 'consumable_id' => $this->tisu->id, 'standard_quantity' => 5, 'actual_quantity' => 1]);

        $this->adjust($standard, 3, ['note' => 'x'])->assertJsonValidationErrors(['standard' => 'Gudang tidak dicek terhadap standar.']);
        $this->postJson("/api/rooms/{$storage->id}/check")->assertJsonValidationErrors(['room' => 'Gudang tidak dicek terhadap standar.']);
        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock")
            ->assertJsonValidationErrors(['standard' => 'Gudang tidak dicek terhadap standar.']);

        $this->assertSame(0, RoomStockTransaction::count());
        $this->assertSame(10, $this->tisu->fresh()->quantity);
        $this->assertSame(1, $standard->fresh()->actual_quantity);
        $this->assertNull($storage->fresh()->last_checked_at);
    }

    public function test_asset_rows_cannot_be_adjusted_or_opened_as_room_card(): void
    {
        $standard = RoomStandard::factory()->create([
            'room_id' => $this->room->id, 'count_method' => CountMethod::Qr, 'asset_type_id' => AssetType::factory()->create()->id, 'actual_quantity' => null,
        ]);

        $this->adjust($standard, 1)->assertJsonValidationErrors(['standard' => 'Baris ini bukan barang stok.']);
        $this->getJson("/api/room-standards/{$standard->id}/transactions")->assertJsonValidationErrors('standard');
    }

    public function test_detach_keeps_history_and_readding_continues_balance_but_unchecked(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 6)->assertOk();
        $this->adjust($standard, 4, ['reason' => 'Terpakai'])->assertOk();

        $this->deleteJson("/api/room-standards/{$standard->id}")->assertOk();
        $this->assertCount(2, $this->ledger());

        $again = $this->addItem(standard: 8);

        $this->assertSame(4, $again->actual_quantity);
        $this->assertNull($again->checked_at);
        $this->getJson("/api/rooms/{$this->room->id}/checklist")
            ->assertJsonPath('data.consumables.0.result', 'Belum dicek')
            ->assertJsonPath('data.consumables.0.present_quantity', null)
            ->assertJsonPath('data.consumables.0.recorded_quantity', 4);

        // Kartu ruangan memuat riwayat sebelum barang dilepas.
        $this->getJson("/api/room-standards/{$again->id}/transactions")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'Terpakai')
            ->assertJsonPath('data.0.created_by', 'Staf SHD')
            ->assertJsonPath('data.1.type', 'Saldo awal');

        // Perubahan berikutnya melanjutkan saldo; bertambah setelah ada riwayat → Koreksi, bukan Saldo awal baru.
        $this->adjust($again, 5, ['note' => 'Tambahan'])->assertOk()->assertJsonPath('transaction.type', 'Koreksi');
        $this->assertBalanceMatchesLedger($again);
    }

    public function test_changing_item_on_a_row_uses_new_items_balance_and_resets_check(): void
    {
        $pulpen = Consumable::factory()->create(['name' => 'Pulpen']);
        $standard = $this->addItem();
        $this->adjust($standard, 2)->assertOk();

        $this->putJson("/api/room-standards/{$standard->id}", ['consumable_id' => $pulpen->id, 'standard_quantity' => 5])->assertOk();
        $this->assertSame(0, $standard->fresh()->actual_quantity);
        $this->assertNull($standard->fresh()->checked_at);

        // Ubah jumlah standar saja: status cek tetap.
        $this->adjust($standard->fresh(), 1)->assertOk();
        $this->putJson("/api/room-standards/{$standard->id}", ['consumable_id' => $pulpen->id, 'standard_quantity' => 9])->assertOk();
        $this->assertNotNull($standard->fresh()->checked_at);
        $this->assertSame(1, $standard->fresh()->actual_quantity);
    }

    public function test_room_with_item_history_cannot_be_deleted(): void
    {
        $standard = $this->addItem();
        $this->adjust($standard, 2)->assertOk();
        $this->deleteJson("/api/room-standards/{$standard->id}")->assertOk();

        $this->deleteJson("/api/rooms/{$this->room->id}")->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertModelExists($this->room);
    }

    public function test_checklist_shows_last_usage_without_extra_queries_per_row(): void
    {
        $pulpen = Consumable::factory()->create(['name' => 'Pulpen']);
        $tisu = $this->addItem();
        $pena = $this->addItem($pulpen);
        $this->adjust($tisu, 10)->assertOk();
        $this->adjust($tisu, 4, ['reason' => 'Terpakai'])->assertOk();
        // Hilang bukan pemakaian.
        $this->adjust($tisu, 3, ['reason' => 'Hilang'])->assertOk();
        $this->adjust($pena, 3)->assertOk();

        $rows = collect($this->getJson("/api/rooms/{$this->room->id}/checklist")->json('data.consumables'))->keyBy('item_name');
        $this->assertSame(['quantity' => 6, 'date' => today()->toDateString()], $rows['Tisu']['last_usage']);
        $this->assertNull($rows['Pulpen']['last_usage']);

        DB::enableQueryLog();
        $this->getJson("/api/rooms/{$this->room->id}/checklist")->assertOk();
        $queries = collect(DB::getQueryLog())->filter(fn (array $q) => str_contains($q['query'], 'room_stock_transactions'));
        $this->assertCount(1, $queries);
    }

    public function test_quantity_and_note_limits_are_validated(): void
    {
        $standard = $this->addItem();

        $this->adjust($standard, -1)->assertJsonValidationErrors(['quantity' => 'Jumlah tidak boleh negatif.']);
        $this->postJson("/api/room-standards/{$standard->id}/adjust", [])->assertJsonValidationErrors(['quantity' => 'Jumlah sekarang wajib diisi.']);
        $this->adjust($standard, 1, ['note' => str_repeat('a', 1001)])->assertJsonValidationErrors(['note' => 'Keterangan maksimal 1000 karakter.']);
    }

    public function test_migration_creates_opening_balance_and_moves_unchecked_flag(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_09_034958_create_room_stock_transactions_table.php');
        $migration->down();

        $storage = Room::factory()->create(['is_storage' => true]);
        $pulpen = Consumable::factory()->create(['name' => 'Pulpen']);
        $row = fn (array $attributes) => DB::table('room_standards')->insertGetId([
            'room_id' => $this->room->id, 'standard_quantity' => 5, 'count_method' => 'Manual', 'created_at' => now(), 'updated_at' => '2026-11-01 08:00:00', ...$attributes,
        ]);
        $checked = $row(['consumable_id' => $this->tisu->id, 'actual_quantity' => 3]);
        $unchecked = $row(['consumable_id' => $pulpen->id, 'actual_quantity' => null]);
        $zero = $row(['room_id' => $storage->id, 'consumable_id' => $this->tisu->id, 'actual_quantity' => 2]);

        $migration->up();

        $this->assertSame([['type' => 'Saldo awal', 'quantity' => 3, 'balance_after' => 3, 'note' => null]], $this->ledger());
        $this->assertSame('2026-11-01', RoomStockTransaction::sole()->transaction_date->toDateString());
        $this->assertNotNull(RoomStandard::find($checked)->checked_at);
        $this->assertNull(RoomStandard::find($unchecked)->checked_at);
        $this->assertSame(0, RoomStandard::find($unchecked)->actual_quantity);
        // Gudang tidak memakai ledger.
        $this->assertSame(2, RoomStandard::find($zero)->actual_quantity);
        $this->assertSame(1, RoomStockTransaction::count());
    }

    public function test_loss_types_migration_runs_and_rolls_back(): void
    {
        /** @var Migration $migration */
        $migration = require database_path('migrations/2026_11_09_044334_add_loss_types_to_room_stock_transactions.php');

        $migration->down();
        $migration->up();

        $standard = $this->addItem();
        $this->adjust($standard, 3)->assertOk();
        $this->adjust($standard, 2, ['reason' => 'Kedaluwarsa'])->assertOk();
        $this->assertSame('Kedaluwarsa', $this->ledger()[1]['type']);
    }
}
