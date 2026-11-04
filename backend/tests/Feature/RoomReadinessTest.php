<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class RoomReadinessTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Department $shd;

    private Department $deptA;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->user);
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->deptA = Department::factory()->create(['name' => 'Dept A']);
        $this->room = Room::factory()->create(['name' => 'Ruang Kelas 1']);
    }

    private function standard(array $overrides = []): RoomStandard
    {
        return RoomStandard::factory()->create([
            'room_id' => $this->room->id,
            'source_department_id' => $this->shd->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => null,
            ...$overrides,
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return [
            'item_name' => 'ATK peserta',
            'source_department_id' => $this->shd->id,
            'standard_quantity' => 10,
            'count_method' => 'Manual',
            ...$overrides,
        ];
    }

    public function test_requires_authentication(): void
    {
        $this->app['auth']->forgetGuards();

        $this->withToken('invalid')->getJson('/api/room-readiness')->assertUnauthorized();
        $this->withToken('invalid')->getJson("/api/rooms/{$this->room->id}/checklist")->assertUnauthorized();
    }

    public function test_copy_endpoint_does_not_exist(): void
    {
        $other = Room::factory()->create();

        $this->postJson("/api/rooms/{$this->room->id}/standards/copy", ['from_room_id' => $other->id])->assertNotFound();
    }

    public function test_qr_row_counts_assets_of_type_in_room_excluding_unavailable_statuses(): void
    {
        $type = AssetType::factory()->create();
        $make = fn (AssetStatus $status, ?Room $room = null) => Asset::factory()->create([
            'asset_type_id' => $type->id, 'room_id' => ($room ?? $this->room)->id, 'status' => $status,
        ]);
        $make(AssetStatus::Tersedia);
        $make(AssetStatus::Tersedia);
        $make(AssetStatus::Dipinjam);
        $make(AssetStatus::Perbaikan);
        $make(AssetStatus::Hilang);
        $make(AssetStatus::TidakLayakPakai);
        $make(AssetStatus::Tersedia, Room::factory()->create());
        $standard = $this->standard(['count_method' => CountMethod::Qr, 'asset_type_id' => $type->id, 'standard_quantity' => 3]);

        $this->getJson("/api/rooms/{$this->room->id}/checklist")
            ->assertOk()
            ->assertJsonPath('data.0.id', $standard->id)
            ->assertJsonPath('data.0.present_quantity', 2)
            ->assertJsonPath('data.0.difference', -1)
            ->assertJsonPath('data.0.result', 'Kurang')
            ->assertJsonPath('meta.summary.shortage_items', 1);
    }

    public function test_manual_row_without_check_is_unchecked_and_zero_is_shortage(): void
    {
        $this->standard(['actual_quantity' => null]);
        $this->standard(['actual_quantity' => 0]);
        $this->standard(['actual_quantity' => 5]);

        $this->getJson("/api/rooms/{$this->room->id}/checklist")
            ->assertOk()
            ->assertJsonPath('data.0.result', 'Belum dicek')
            ->assertJsonPath('data.0.difference', null)
            ->assertJsonPath('data.1.result', 'Kurang')
            ->assertJsonPath('data.1.difference', -5)
            ->assertJsonPath('data.2.result', 'Sesuai')
            ->assertJsonPath('meta.summary', [
                'total_items' => 3, 'shortage_items' => 1, 'unchecked_items' => 1, 'is_complete' => false,
            ]);
    }

    public function test_store_stock_row_takes_source_from_consumable_provider(): void
    {
        $consumable = Consumable::factory()->create(['provider_department_id' => $this->deptA->id]);

        // Sumber yang dikirim diabaikan: selalu penyedia barang.
        $this->postJson("/api/rooms/{$this->room->id}/standards", $this->payload(['consumable_id' => $consumable->id]))
            ->assertCreated()
            ->assertJsonPath('data.result', 'Belum dicek')
            ->assertJsonPath('data.source_department.name', 'Dept A')
            ->assertJsonPath('data.needs_completion', false)
            ->assertJsonPath('data.fulfillment.type', 'stok')
            ->assertJsonPath('data.fulfillment.consumable.id', $consumable->id)
            ->assertJsonPath('data.fulfillment.consumable.provider_department.name', 'Dept A');
    }

    public function test_store_asset_row_keeps_chosen_source(): void
    {
        $type = AssetType::factory()->create();

        $this->postJson("/api/rooms/{$this->room->id}/standards", $this->payload(['count_method' => 'QR', 'asset_type_id' => $type->id, 'source_department_id' => $this->deptA->id]))
            ->assertCreated()
            ->assertJsonPath('data.source_department.name', 'Dept A')
            ->assertJsonPath('data.needs_completion', false)
            ->assertJsonPath('data.fulfillment', null);
    }

    public function test_standard_validation_rules(): void
    {
        $consumable = Consumable::factory()->create();
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $this->deptA->id]);
        $type = AssetType::factory()->create();
        $url = "/api/rooms/{$this->room->id}/standards";
        $stock = ['consumable_id' => $consumable->id];

        $this->postJson($url, $this->payload(['count_method' => 'QR']))->assertJsonValidationErrors('asset_type_id');
        $this->postJson($url, $this->payload(['count_method' => 'QR', 'asset_type_id' => $type->id, 'source_department_id' => null]))
            ->assertJsonValidationErrors('source_department_id');
        $this->postJson($url, $this->payload(['count_method' => 'QR', 'asset_type_id' => $type->id, ...$stock]))
            ->assertJsonValidationErrors('consumable_id');
        $this->postJson($url, $this->payload())->assertJsonValidationErrors('consumable_id');
        $this->postJson($url, $this->payload([...$stock, 'asset_type_id' => $type->id]))->assertJsonValidationErrors('asset_type_id');
        $this->postJson($url, $this->payload([...$stock, 'fulfillment_note' => 'Gudang']))->assertJsonValidationErrors('fulfillment_note');
        $this->postJson($url, $this->payload([...$stock, 'catalog_id' => $catalog->id]))->assertJsonValidationErrors('catalog_id');
        $this->postJson($url, $this->payload([...$stock, 'actual_quantity' => 3]))->assertJsonValidationErrors('actual_quantity');
        $this->postJson($url, $this->payload([...$stock, 'standard_quantity' => 0]))->assertJsonValidationErrors('standard_quantity');
    }

    public function test_update_changes_definition_only_and_delete_is_permanent(): void
    {
        $consumable = Consumable::factory()->create();
        $standard = $this->standard(['actual_quantity' => 4, 'consumable_id' => $consumable->id]);
        $payload = ['consumable_id' => $consumable->id];

        $this->putJson("/api/room-standards/{$standard->id}", $this->payload([...$payload, 'standard_quantity' => 6]))
            ->assertOk()
            ->assertJsonPath('data.standard_quantity', 6)
            ->assertJsonPath('data.present_quantity', 4);

        $this->putJson("/api/room-standards/{$standard->id}", $this->payload([...$payload, 'actual_quantity' => 9]))
            ->assertJsonValidationErrors('actual_quantity');

        $this->deleteJson("/api/room-standards/{$standard->id}")->assertOk();
        $this->assertDatabaseMissing('room_standards', ['id' => $standard->id]);
    }

    public function test_qr_row_lists_counted_asset_codes_and_not_counted_statuses(): void
    {
        $type = AssetType::factory()->create();
        $asset = fn (string $code, AssetStatus $status) => Asset::factory()->create([
            'code' => $code, 'asset_type_id' => $type->id, 'room_id' => $this->room->id, 'status' => $status,
        ]);

        $second = $asset('SHD-PRJ-002', AssetStatus::Tersedia);
        $first = $asset('SHD-PRJ-001', AssetStatus::Tersedia);
        $asset('SHD-PRJ-003', AssetStatus::Dipinjam);
        $asset('SHD-PRJ-004', AssetStatus::Dipinjam);
        $asset('SHD-PRJ-005', AssetStatus::Perbaikan);
        $asset('SHD-PRJ-006', AssetStatus::Hilang);
        $asset('SHD-PRJ-007', AssetStatus::TidakLayakPakai);
        Asset::factory()->create(['code' => 'SHD-PRJ-008', 'asset_type_id' => $type->id, 'status' => AssetStatus::Tersedia]);

        $this->standard(['count_method' => CountMethod::Qr, 'asset_type_id' => $type->id, 'standard_quantity' => 2]);

        $this->getJson("/api/rooms/{$this->room->id}/checklist")
            ->assertOk()
            ->assertJsonPath('data.0.present_quantity', 2)
            ->assertJsonPath('data.0.assets', [['id' => $first->id, 'code' => 'SHD-PRJ-001'], ['id' => $second->id, 'code' => 'SHD-PRJ-002']])
            ->assertExactJsonStructure(['data' => [['id', 'room_id', 'item_name', 'source_department', 'standard_quantity', 'count_method', 'asset_type', 'assets', 'not_counted', 'present_quantity', 'difference', 'shortage', 'result', 'fulfillment', 'needs_completion', 'can_fill_from_stock', 'can_request']], 'meta'])
            ->assertJsonPath('data.0.not_counted', ['Dipinjam' => 2, 'Perbaikan' => 1, 'Hilang' => 1]);
    }

    public function test_checklist_reads_qr_assets_with_one_query(): void
    {
        foreach (range(1, 3) as $i) {
            $type = AssetType::factory()->create();
            Asset::factory()->create(['asset_type_id' => $type->id, 'room_id' => $this->room->id]);
            $this->standard(['count_method' => CountMethod::Qr, 'asset_type_id' => $type->id]);
        }

        DB::enableQueryLog();
        $this->getJson("/api/rooms/{$this->room->id}/checklist")->assertOk();
        $assetQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "assets"'));

        $this->assertCount(1, $assetQueries);
    }

    public function test_row_without_assets_and_stock_row_have_empty_asset_data(): void
    {
        $this->standard(['count_method' => CountMethod::Qr, 'asset_type_id' => AssetType::factory()->create()->id]);
        $this->standard(['consumable_id' => Consumable::factory()->create()->id]);

        $this->getJson("/api/rooms/{$this->room->id}/checklist")
            ->assertOk()
            ->assertJsonPath('data.0.assets', [])
            ->assertJsonPath('data.0.not_counted', [])
            ->assertJsonPath('data.1.assets', [])
            ->assertJsonPath('data.1.not_counted', []);
    }

    public function test_actions_follow_stock(): void
    {
        $url = "/api/rooms/{$this->room->id}/checklist";
        $row = fn (RoomStandard $standard) => collect($this->getJson($url)->assertOk()->json('data'))->firstWhere('id', $standard->id);

        $inStock = Consumable::factory()->create(['quantity' => 3]);
        $outOfStock = Consumable::factory()->create(['quantity' => 0]);

        $fill = $this->standard(['actual_quantity' => 0, 'consumable_id' => $inStock->id]);
        $request = $this->standard(['actual_quantity' => 0, 'consumable_id' => $outOfStock->id]);
        $requestOnly = $this->standard(['actual_quantity' => 0, 'source_department_id' => $this->deptA->id]);
        $enough = $this->standard(['actual_quantity' => 5, 'consumable_id' => $inStock->id]);

        $this->assertTrue($row($fill)['can_fill_from_stock']);
        $this->assertFalse($row($fill)['can_request']);
        $this->assertFalse($row($request)['can_fill_from_stock']);
        $this->assertTrue($row($request)['can_request']);
        $this->assertFalse($row($requestOnly)['can_fill_from_stock']);
        $this->assertTrue($row($requestOnly)['can_request']);
        $this->assertFalse($row($enough)['can_fill_from_stock']);
        $this->assertFalse($row($enough)['can_request']);

        // Stok habis di tengah pengisian → aksi berganti ke Buat permintaan.
        $this->postJson("/api/room-standards/{$fill->id}/fill-from-stock")->assertOk()->assertJsonPath('remaining_shortage', 2);
        $this->assertFalse($row($fill)['can_fill_from_stock']);
        $this->assertTrue($row($fill)['can_request']);
    }

    public function test_other_unit_row_is_stored_without_stock_and_cannot_be_filled(): void
    {
        $url = "/api/rooms/{$this->room->id}/standards";
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $this->deptA->id, 'item_name' => 'Tisu Dus']);
        $otherCatalog = RequestCatalog::factory()->create();

        $this->postJson($url, $this->payload(['source_department_id' => $this->deptA->id, 'catalog_id' => $otherCatalog->id]))
            ->assertJsonValidationErrors(['catalog_id' => 'Katalog harus dari departemen yang sama.']);

        $id = $this->postJson($url, $this->payload(['item_name' => 'Tisu', 'source_department_id' => $this->deptA->id, 'catalog_id' => $catalog->id]))
            ->assertCreated()
            ->assertJsonPath('data.needs_completion', false)
            ->assertJsonPath('data.fulfillment.type', 'unit_lain')
            ->assertJsonPath('data.fulfillment.catalog.item_name', 'Tisu Dus')
            ->assertJsonPath('data.fulfillment.provider_department.name', 'Dept A')
            ->assertJsonPath('data.can_request', false)
            ->json('data.id');

        $this->postJson("/api/rooms/{$this->room->id}/check", ['items' => [['id' => $id, 'actual_quantity' => 1]]])->assertOk();

        $row = collect($this->getJson("/api/rooms/{$this->room->id}/checklist")->json('data'))->firstWhere('id', $id);
        $this->assertTrue($row['can_request']);
        $this->assertFalse($row['can_fill_from_stock']);

        $this->postJson("/api/room-standards/{$id}/fill-from-stock")->assertJsonValidationErrors('standard');

        // Katalog dihapus: tetap Barang unit lain, tanpa katalog.
        $this->deleteJson("/api/request-catalog/{$catalog->id}")->assertOk();
        $this->getJson("/api/rooms/{$this->room->id}/checklist")->assertOk()->assertJsonPath('data.0.needs_completion', false)->assertJsonPath('data.0.fulfillment.catalog', null);
    }

    public function test_shd_row_without_stock_needs_completion(): void
    {
        $old = $this->standard(['actual_quantity' => 1, 'fulfillment_note' => 'Gudang']);
        $consumable = Consumable::factory()->create(['provider_department_id' => $this->deptA->id]);

        $row = collect($this->getJson("/api/rooms/{$this->room->id}/checklist")->assertOk()->json('data'))->firstWhere('id', $old->id);

        $this->assertTrue($row['needs_completion']);
        $this->assertNull($row['fulfillment']);
        $this->assertFalse($row['can_fill_from_stock']);
        $this->assertFalse($row['can_request']);
        $this->assertSame('Kurang', $row['result']);

        // Dilengkapi lewat PUT: catatan lama dilepas, jumlah Ada tetap, sumber mengikuti penyedia.
        $this->putJson("/api/room-standards/{$old->id}", $this->payload(['consumable_id' => $consumable->id]))
            ->assertOk()
            ->assertJsonPath('data.needs_completion', false)
            ->assertJsonPath('data.present_quantity', 1)
            ->assertJsonPath('data.source_department.name', 'Dept A');
        $this->assertNull($old->fresh()->fulfillment_note);
    }

    public function test_save_check_stores_manual_quantities_and_checker(): void
    {
        $manual = $this->standard();
        $qr = $this->standard(['count_method' => CountMethod::Qr, 'asset_type_id' => AssetType::factory()->create()->id]);
        $otherRoom = RoomStandard::factory()->create();
        $url = "/api/rooms/{$this->room->id}/check";

        $this->postJson($url, ['items' => [['id' => $qr->id, 'actual_quantity' => 1]]])->assertJsonValidationErrors('items');
        $this->postJson($url, ['items' => [['id' => $otherRoom->id, 'actual_quantity' => 1]]])->assertJsonValidationErrors('items');
        $this->postJson($url, ['items' => [['id' => $manual->id, 'actual_quantity' => -1]]])->assertJsonValidationErrors('items.0.actual_quantity');

        $this->postJson($url, ['items' => [['id' => $manual->id, 'actual_quantity' => 3]]])
            ->assertOk()
            ->assertJsonPath('data.0.present_quantity', 3)
            ->assertJsonPath('meta.last_checked_by', 'Staf SHD');

        $this->room->refresh();
        $this->assertNotNull($this->room->last_checked_at);
        $this->assertSame($this->user->id, $this->room->last_checked_by);
    }

    public function test_fill_from_stock_uses_min_of_shortage_and_stock(): void
    {
        $consumable = Consumable::factory()->create(['quantity' => 3]);
        $standard = $this->standard(['actual_quantity' => 0, 'consumable_id' => $consumable->id]);

        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock")
            ->assertOk()
            ->assertJsonPath('filled', 3)
            ->assertJsonPath('remaining_shortage', 2)
            ->assertJsonPath('stock_remaining', 0)
            ->assertJsonPath('data.result', 'Kurang');

        $this->assertSame(0, $consumable->fresh()->quantity);
        $this->assertSame(3, $standard->fresh()->actual_quantity);

        // Stok habis: ditolak, tidak negatif
        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock")->assertJsonValidationErrors('standard');
        $this->assertSame(0, $consumable->fresh()->quantity);

        // Stok cukup: hanya sebanyak kekurangan
        $consumable->update(['quantity' => 10]);
        $this->postJson("/api/room-standards/{$standard->id}/fill-from-stock")
            ->assertOk()
            ->assertJsonPath('filled', 2)
            ->assertJsonPath('remaining_shortage', 0)
            ->assertJsonPath('data.result', 'Sesuai');
        $this->assertSame(8, $consumable->fresh()->quantity);
    }

    public function test_fill_from_stock_rejects_unchecked_or_unlinked_rows(): void
    {
        $consumable = Consumable::factory()->create(['quantity' => 10]);
        $unchecked = $this->standard(['consumable_id' => $consumable->id]);
        $unlinked = $this->standard(['actual_quantity' => 0]);

        $this->postJson("/api/room-standards/{$unchecked->id}/fill-from-stock")->assertJsonValidationErrors('standard');
        $this->postJson("/api/room-standards/{$unlinked->id}/fill-from-stock")->assertJsonValidationErrors('standard');
        $this->assertSame(10, $consumable->fresh()->quantity);
    }

    public function test_readiness_summary_per_building(): void
    {
        $building = $this->room->building;
        $complete = Room::factory()->create(['building_id' => $building->id, 'name' => 'Ruang Rapat']);
        RoomStandard::factory()->create(['room_id' => $complete->id, 'standard_quantity' => 2, 'actual_quantity' => 2]);
        $this->standard(['actual_quantity' => 1]);
        $this->standard(['actual_quantity' => null]);
        Building::factory()->create(['name' => 'Gedung Kosong']);

        $response = $this->getJson('/api/room-readiness')->assertOk();
        $data = collect($response->json('data'))->keyBy('id');
        $rooms = collect($data[$building->id]['rooms'])->keyBy('id');

        $this->assertSame(2, $data[$building->id]['rooms_count']);
        $this->assertSame(1, $data[$building->id]['incomplete_rooms_count']);
        $this->assertSame(1, $rooms[$this->room->id]['shortage_items']);
        $this->assertSame(1, $rooms[$this->room->id]['unchecked_items']);
        $this->assertTrue($rooms[$complete->id]['is_complete']);
        $this->assertCount(2, $data);
    }
}
