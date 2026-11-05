<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
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

    private Department $deptA;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->user);
        Department::factory()->internal()->create(['name' => 'SHD']);
        $this->deptA = Department::factory()->create(['name' => 'Dept A']);
        $this->room = Room::factory()->create(['name' => 'Ruang Kelas 1']);
    }

    private function standard(array $overrides = []): RoomStandard
    {
        return RoomStandard::factory()->create([
            'room_id' => $this->room->id,
            'standard_quantity' => 5,
            'count_method' => CountMethod::Manual,
            'actual_quantity' => null,
            ...$overrides,
        ]);
    }

    private function assetStandard(AssetType $type, int $quantity = 1): RoomStandard
    {
        return $this->standard(['count_method' => CountMethod::Qr, 'asset_type_id' => $type->id, 'standard_quantity' => $quantity]);
    }

    private function checklist(): array
    {
        return $this->getJson("/api/rooms/{$this->room->id}/checklist")->assertOk()->json();
    }

    public function test_quantities_above_integer_column_limit_are_rejected(): void
    {
        $this->postJson("/api/rooms/{$this->room->id}/standards", ['consumable_id' => Consumable::factory()->create()->id, 'standard_quantity' => 2147483648])
            ->assertJsonValidationErrors('standard_quantity');

        $manual = $this->standard();

        $this->postJson("/api/rooms/{$this->room->id}/check", ['items' => [['id' => $manual->id, 'actual_quantity' => 2147483648]]])
            ->assertJsonValidationErrors('items.0.actual_quantity');
        $this->assertNull($manual->fresh()->actual_quantity);
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

    public function test_room_without_standard_still_lists_assets_grouped_by_type(): void
    {
        $proyektor = AssetType::factory()->create(['name' => 'Proyektor', 'code' => 'PRJ']);
        $kursi = AssetType::factory()->create(['name' => 'Kursi', 'code' => 'KRS']);
        $make = fn (AssetType $type, string $code, AssetStatus $status) => Asset::factory()->create([
            'code' => $code, 'asset_type_id' => $type->id, 'room_id' => $this->room->id, 'status' => $status,
        ]);

        $second = $make($proyektor, 'SHD-PRJ-002', AssetStatus::Dipinjam);
        $first = $make($proyektor, 'SHD-PRJ-001', AssetStatus::Tersedia);
        $make($proyektor, 'SHD-PRJ-003', AssetStatus::Perbaikan);
        $make($proyektor, 'SHD-PRJ-004', AssetStatus::TidakLayakPakai);
        $make($kursi, 'SHD-KRS-001', AssetStatus::Tersedia);
        Asset::factory()->create(['asset_type_id' => $proyektor->id, 'status' => AssetStatus::Tersedia]);

        $response = $this->checklist();
        $groups = $response['data']['assets'];

        $this->assertSame(['Kursi', 'Proyektor'], array_column(array_column($groups, 'asset_type'), 'name'));
        $this->assertSame(3, $groups[1]['total']);
        $this->assertSame(
            [
                ['id' => $first->id, 'code' => 'SHD-PRJ-001', 'status' => 'Tersedia'],
                ['id' => $second->id, 'code' => 'SHD-PRJ-002', 'status' => 'Dipinjam'],
            ],
            array_slice($groups[1]['assets'], 0, 2),
        );
        $this->assertSame(['Dipinjam' => 1, 'Perbaikan' => 1], $groups[1]['not_counted']);
        // Standar 0: jumlah Ada tampil, tanpa Selisih/Hasil dan tidak dihitung Kurang.
        $this->assertNull($groups[1]['standard_id']);
        $this->assertSame(0, $groups[1]['standard_quantity']);
        $this->assertSame(1, $groups[1]['present_quantity']);
        $this->assertNull($groups[1]['difference']);
        $this->assertNull($groups[1]['result']);
        $this->assertSame(['total_items' => 0, 'shortage_items' => 0, 'unchecked_items' => 0, 'is_complete' => false], $response['meta']['summary']);
    }

    public function test_asset_standard_counts_only_available_statuses(): void
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
        $standard = $this->assetStandard($type, 3);

        $response = $this->checklist();
        $group = $response['data']['assets'][0];

        $this->assertSame($standard->id, $group['standard_id']);
        $this->assertSame(3, $group['standard_quantity']);
        $this->assertSame(2, $group['present_quantity']);
        $this->assertSame(-1, $group['difference']);
        $this->assertSame('Kurang', $group['result']);
        $this->assertSame(1, $response['meta']['summary']['shortage_items']);
    }

    public function test_type_with_standard_but_no_asset_is_shown_as_shortage(): void
    {
        // Standar 0 tanpa aset tidak tampil.
        AssetType::factory()->create(['name' => 'Kursi']);
        $this->assetStandard(AssetType::factory()->create(['name' => 'Flipchart']));

        $this->assertCount(1, $this->checklist()['data']['assets']);

        $group = $this->checklist()['data']['assets'][0];

        $this->assertSame('Flipchart', $group['asset_type']['name']);
        $this->assertSame(0, $group['total']);
        $this->assertSame([], $group['assets']);
        $this->assertSame('Kurang', $group['result']);
    }

    public function test_checklist_reads_assets_without_query_per_type(): void
    {
        foreach (range(1, 3) as $i) {
            $type = AssetType::factory()->create();
            Asset::factory()->create(['asset_type_id' => $type->id, 'room_id' => $this->room->id]);
            $this->assetStandard($type);
        }

        DB::enableQueryLog();
        $this->checklist();
        $assetQueries = collect(DB::getQueryLog())->filter(fn (array $query) => str_contains($query['query'], 'from "assets"'));

        $this->assertCount(2, $assetQueries);
    }

    public function test_consumable_without_check_is_unchecked_and_zero_is_shortage(): void
    {
        $this->standard(['actual_quantity' => null]);
        $this->standard(['actual_quantity' => 0]);
        $this->standard(['actual_quantity' => 5]);

        $response = $this->checklist();
        $rows = $response['data']['consumables'];

        $this->assertSame('Belum dicek', $rows[0]['result']);
        $this->assertNull($rows[0]['difference']);
        $this->assertSame('Kurang', $rows[1]['result']);
        $this->assertSame(-5, $rows[1]['difference']);
        $this->assertSame('Sesuai', $rows[2]['result']);
        $this->assertSame(['total_items' => 3, 'shortage_items' => 1, 'unchecked_items' => 1, 'is_complete' => false], $response['meta']['summary']);
    }

    public function test_store_consumable_row_uses_stock_item_and_provider(): void
    {
        $consumable = Consumable::factory()->create(['name' => 'Tisu', 'provider_department_id' => $this->deptA->id]);

        $this->postJson("/api/rooms/{$this->room->id}/standards", ['consumable_id' => $consumable->id, 'standard_quantity' => 10])
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'Tisu')
            ->assertJsonPath('data.count_method', 'Manual')
            ->assertJsonPath('data.result', 'Belum dicek')
            ->assertJsonPath('data.consumable.id', $consumable->id)
            ->assertJsonPath('data.consumable.provider_department.name', 'Dept A')
            ->assertJsonPath('data.asset_type', null);
    }

    public function test_store_asset_type_standard(): void
    {
        $type = AssetType::factory()->create(['name' => 'Proyektor']);

        $this->postJson("/api/rooms/{$this->room->id}/standards", ['asset_type_id' => $type->id, 'standard_quantity' => 2])
            ->assertCreated()
            ->assertJsonPath('data.item_name', 'Proyektor')
            ->assertJsonPath('data.count_method', 'QR')
            ->assertJsonPath('data.present_quantity', 0)
            ->assertJsonPath('data.consumable', null);
    }

    public function test_standard_validation_rules(): void
    {
        $consumable = Consumable::factory()->create();
        $type = AssetType::factory()->create();
        $url = "/api/rooms/{$this->room->id}/standards";

        $this->postJson($url, ['standard_quantity' => 1])
            ->assertJsonValidationErrors(['asset_type_id' => 'Pilih jenis aset atau barang stok.']);
        $this->postJson($url, ['asset_type_id' => $type->id, 'consumable_id' => $consumable->id, 'standard_quantity' => 1])
            ->assertJsonValidationErrors(['asset_type_id' => 'Pilih salah satu: jenis aset atau barang stok.']);
        $this->postJson($url, ['consumable_id' => $consumable->id, 'actual_quantity' => 3, 'standard_quantity' => 1])->assertJsonValidationErrors('actual_quantity');
        $this->postJson($url, ['consumable_id' => $consumable->id, 'standard_quantity' => 0])->assertJsonValidationErrors('standard_quantity');
        $this->postJson($url, ['consumable_id' => 999, 'standard_quantity' => 1])->assertJsonValidationErrors('consumable_id');
        $this->assertDatabaseCount('room_standards', 0);
    }

    public function test_same_type_or_item_only_once_per_room(): void
    {
        $consumable = Consumable::factory()->create();
        $type = AssetType::factory()->create();
        $url = "/api/rooms/{$this->room->id}/standards";

        $row = $this->postJson($url, ['consumable_id' => $consumable->id, 'standard_quantity' => 1])->assertCreated()->json('data.id');
        $this->postJson($url, ['asset_type_id' => $type->id, 'standard_quantity' => 1])->assertCreated();

        $this->postJson($url, ['consumable_id' => $consumable->id, 'standard_quantity' => 2])
            ->assertJsonValidationErrors(['consumable_id' => 'Barang ini sudah ada di ruangan ini.']);
        $this->postJson($url, ['asset_type_id' => $type->id, 'standard_quantity' => 2])
            ->assertJsonValidationErrors(['asset_type_id' => 'Jenis aset ini sudah punya standar di ruangan ini.']);

        // Ubah barisnya sendiri tetap boleh; ruangan lain boleh memakai barang yang sama.
        $this->putJson("/api/room-standards/{$row}", ['consumable_id' => $consumable->id, 'standard_quantity' => 3])->assertOk();
        $this->postJson('/api/rooms/'.Room::factory()->create()->id.'/standards', ['consumable_id' => $consumable->id, 'standard_quantity' => 1])->assertCreated();
    }

    public function test_update_changes_definition_only_and_delete_is_permanent(): void
    {
        $consumable = Consumable::factory()->create(['name' => 'Tisu']);
        $standard = $this->standard(['actual_quantity' => 4, 'consumable_id' => $consumable->id]);
        $payload = ['consumable_id' => $consumable->id];

        $this->putJson("/api/room-standards/{$standard->id}", [...$payload, 'standard_quantity' => 6])
            ->assertOk()
            ->assertJsonPath('data.standard_quantity', 6)
            ->assertJsonPath('data.present_quantity', 4);

        $this->putJson("/api/room-standards/{$standard->id}", [...$payload, 'standard_quantity' => 6, 'actual_quantity' => 9])
            ->assertJsonValidationErrors('actual_quantity');

        // Ganti barang: jumlah Ada lama tidak berlaku.
        $this->putJson("/api/room-standards/{$standard->id}", ['consumable_id' => Consumable::factory()->create()->id, 'standard_quantity' => 6])
            ->assertOk()
            ->assertJsonPath('data.present_quantity', null);

        $this->deleteJson("/api/room-standards/{$standard->id}")->assertOk();
        $this->assertDatabaseMissing('room_standards', ['id' => $standard->id]);
    }

    public function test_actions_follow_stock(): void
    {
        $row = fn (RoomStandard $standard) => collect($this->checklist()['data']['consumables'])->firstWhere('id', $standard->id);

        $inStock = Consumable::factory()->create(['quantity' => 3]);
        $outOfStock = Consumable::factory()->create(['quantity' => 0]);
        $plenty = Consumable::factory()->create(['quantity' => 3]);

        $fill = $this->standard(['actual_quantity' => 0, 'consumable_id' => $inStock->id]);
        $request = $this->standard(['actual_quantity' => 0, 'consumable_id' => $outOfStock->id]);
        $enough = $this->standard(['actual_quantity' => 5, 'consumable_id' => $plenty->id]);

        $this->assertTrue($row($fill)['can_fill_from_stock']);
        $this->assertFalse($row($fill)['can_request']);
        $this->assertFalse($row($request)['can_fill_from_stock']);
        $this->assertTrue($row($request)['can_request']);
        $this->assertFalse($row($enough)['can_fill_from_stock']);
        $this->assertFalse($row($enough)['can_request']);

        // Stok habis di tengah pengambilan → aksi berganti ke Ajukan permintaan.
        $this->postJson("/api/room-standards/{$fill->id}/fill-from-stock")->assertOk()->assertJsonPath('remaining_shortage', 2);
        $this->assertFalse($row($fill)['can_fill_from_stock']);
        $this->assertTrue($row($fill)['can_request']);
    }

    public function test_save_check_stores_consumable_quantities_and_checker(): void
    {
        $manual = $this->standard();
        $qr = $this->assetStandard(AssetType::factory()->create());
        $otherRoom = RoomStandard::factory()->create();
        $url = "/api/rooms/{$this->room->id}/check";

        $this->postJson($url, ['items' => [['id' => $qr->id, 'actual_quantity' => 1]]])->assertJsonValidationErrors('items');
        $this->postJson($url, ['items' => [['id' => $otherRoom->id, 'actual_quantity' => 1]]])->assertJsonValidationErrors('items');
        $this->postJson($url, ['items' => [['id' => $manual->id, 'actual_quantity' => -1]]])->assertJsonValidationErrors('items.0.actual_quantity');

        $this->postJson($url, ['items' => [['id' => $manual->id, 'actual_quantity' => 3]]])
            ->assertOk()
            ->assertJsonPath('data.consumables.0.present_quantity', 3)
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

    public function test_fill_from_stock_rejects_unchecked_or_asset_rows(): void
    {
        $consumable = Consumable::factory()->create(['quantity' => 10]);
        $unchecked = $this->standard(['consumable_id' => $consumable->id]);
        $asset = $this->assetStandard(AssetType::factory()->create());

        $this->postJson("/api/room-standards/{$unchecked->id}/fill-from-stock")->assertJsonValidationErrors('standard');
        $this->postJson("/api/room-standards/{$asset->id}/fill-from-stock")->assertJsonValidationErrors('standard');
        $this->assertSame(10, $consumable->fresh()->quantity);
    }

    public function test_readiness_lists_all_rooms_and_room_without_standard_is_not_complete(): void
    {
        $building = $this->room->building;
        $complete = Room::factory()->create(['building_id' => $building->id, 'name' => 'Ruang Rapat']);
        $empty = Room::factory()->create(['building_id' => $building->id, 'name' => 'Lt. 8 – Ruang A']);
        RoomStandard::factory()->create(['room_id' => $complete->id, 'standard_quantity' => 2, 'actual_quantity' => 2]);
        $this->standard(['actual_quantity' => 1]);
        $this->standard(['actual_quantity' => null]);
        Building::factory()->create(['name' => 'Gedung Kosong']);

        $response = $this->getJson('/api/room-readiness')->assertOk();
        $data = collect($response->json('data'))->keyBy('id');
        $rooms = collect($data[$building->id]['rooms'])->keyBy('id');

        $this->assertSame(3, $data[$building->id]['rooms_count']);
        $this->assertSame(1, $data[$building->id]['incomplete_rooms_count']);
        $this->assertSame(1, $rooms[$this->room->id]['shortage_items']);
        $this->assertSame(1, $rooms[$this->room->id]['unchecked_items']);
        $this->assertTrue($rooms[$complete->id]['is_complete']);
        $this->assertFalse($rooms[$empty->id]['is_complete']);
        $this->assertSame(0, $rooms[$empty->id]['total_items']);
        $this->assertCount(2, $data);
    }
}
