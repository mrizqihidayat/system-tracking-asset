<?php

namespace Tests\Feature;

use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Tanda gudang pada ruangan (SPEC §4, §8, §10): lokasi simpan stok & pengecualian dari hitungan kesiapan.
 */
class RoomStorageTest extends TestCase
{
    use RefreshDatabase;

    private Building $building;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        Department::factory()->internal()->create(['name' => 'SHD']);
        $this->building = Building::factory()->create(['name' => 'Gedung IHS']);
    }

    private function room(array $overrides = []): Room
    {
        return Room::factory()->for($this->building)->create($overrides);
    }

    public function test_room_is_not_storage_by_default_and_can_be_marked_on_create_and_update(): void
    {
        $url = "/api/buildings/{$this->building->id}/rooms";

        $id = $this->postJson($url, ['name' => 'Ruang Kelas'])->assertCreated()->assertJsonPath('data.is_storage', false)->json('data.id');
        $this->postJson($url, ['name' => 'Gudang', 'is_storage' => true])->assertCreated()->assertJsonPath('data.is_storage', true);
        $this->postJson($url, ['name' => 'Lain', 'is_storage' => 'mungkin'])->assertJsonValidationErrors('is_storage');

        $this->putJson("/api/rooms/{$id}", ['name' => 'Ruang Kelas', 'is_storage' => true])->assertOk()->assertJsonPath('data.is_storage', true);
        $this->putJson("/api/rooms/{$id}", ['name' => 'Ruang Kelas 2'])->assertOk()->assertJsonPath('data.is_storage', true);
        $this->putJson("/api/rooms/{$id}", ['name' => 'Ruang Kelas 2', 'is_storage' => false])->assertOk()->assertJsonPath('data.is_storage', false);

        $this->getJson('/api/rooms')->assertOk()->assertJsonPath('data.0.name', 'Gudang')->assertJsonPath('data.0.is_storage', true);
    }

    public function test_storage_mark_cannot_be_removed_while_room_holds_stock(): void
    {
        $gudang = $this->room(['is_storage' => true]);
        Consumable::factory()->count(2)->sequence(['name' => 'Pulpen'], ['name' => 'Pensil'])->create(['room_id' => $gudang->id]);

        $this->putJson("/api/rooms/{$gudang->id}", ['name' => 'Gudang', 'is_storage' => false])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['is_storage' => 'Ruangan masih menjadi lokasi simpan 2 barang stok.']);
        $this->assertTrue($gudang->fresh()->is_storage);

        // Tetap gudang: nama boleh diubah.
        $this->putJson("/api/rooms/{$gudang->id}", ['name' => 'Gudang Baru', 'is_storage' => true])->assertOk();

        Consumable::query()->update(['room_id' => null]);
        $this->putJson("/api/rooms/{$gudang->id}", ['name' => 'Gudang Baru', 'is_storage' => false])->assertOk();
    }

    public function test_stock_location_must_be_storage_room(): void
    {
        $biasa = $this->room();
        $gudang = $this->room(['is_storage' => true]);
        $payload = ['name' => 'Pulpen', 'unit' => 'pcs', 'quantity' => 1, 'min_quantity' => 0, 'provider_department_id' => Department::factory()->create()->id];

        $this->postJson('/api/consumables', [...$payload, 'room_id' => $biasa->id])
            ->assertJsonValidationErrors(['room_id' => 'Lokasi simpan harus ruangan gudang.']);

        $id = $this->postJson('/api/consumables', [...$payload, 'room_id' => $gudang->id])->assertCreated()->assertJsonPath('data.room.is_storage', true)->json('data.id');

        $this->putJson("/api/consumables/{$id}", ['room_id' => $biasa->id])
            ->assertJsonValidationErrors(['room_id' => 'Lokasi simpan harus ruangan gudang.']);
        $this->putJson("/api/consumables/{$id}", ['room_id' => null])->assertOk()->assertJsonPath('data.room', null);
    }

    public function test_stored_stock_section_only_for_storage_room(): void
    {
        $gudang = $this->room(['is_storage' => true]);
        $biasa = $this->room();
        Consumable::factory()->create(['name' => 'Pulpen', 'room_id' => $gudang->id]);
        // Data yang menyimpang (mis. ditulis langsung) tetap tidak ditampilkan di ruangan biasa.
        Consumable::factory()->create(['name' => 'Pensil', 'room_id' => $biasa->id]);

        $this->getJson("/api/rooms/{$gudang->id}/checklist")->assertJsonPath('data.stored_stock.0.name', 'Pulpen');
        $this->getJson("/api/rooms/{$biasa->id}/checklist")->assertJsonPath('data.stored_stock', []);
    }

    public function test_storage_room_is_excluded_from_readiness_counts(): void
    {
        $short = $this->room(['name' => 'Kelas Kurang']);
        $this->room(['name' => 'Kelas Kosong']);
        $gudangShort = $this->room(['name' => 'Gudang Kurang', 'is_storage' => true]);
        $this->room(['name' => 'Gudang Kosong', 'is_storage' => true]);

        foreach ([$short, $gudangShort] as $room) {
            RoomStandard::factory()->create(['room_id' => $room->id, 'standard_quantity' => 5, 'actual_quantity' => 1]);
        }

        $building = collect($this->getJson('/api/room-readiness')->assertOk()->json('data'))->firstWhere('id', $this->building->id);
        $rooms = collect($building['rooms'])->keyBy('name');

        // Halaman kelengkapan tetap menampilkan semua ruangan, tetapi gudang tidak dihitung belum lengkap.
        $this->assertSame(4, $building['rooms_count']);
        $this->assertSame(1, $building['incomplete_rooms_count']);
        $this->assertTrue($rooms['Gudang Kurang']['is_storage']);
        $this->assertSame(1, $rooms['Gudang Kurang']['shortage_items']);
        $this->assertFalse($rooms['Kelas Kurang']['is_storage']);

        $cards = $this->getJson('/api/dashboard/cards')->assertOk()->json('data.incomplete_rooms');
        $this->assertSame(['incomplete' => 1, 'total_rooms' => 2, 'total_buildings' => 1, 'incomplete_buildings' => 1], $cards);

        $panel = $this->getJson('/api/dashboard/room-readiness')->assertOk()->json('data.0');
        $this->assertSame(2, $panel['rooms_count']);
        $this->assertSame(1, $panel['rooms_without_standards']);
        $this->assertSame(1, $panel['incomplete_rooms_count']);
        $this->assertSame(1, $panel['shortage_items']);
        $this->assertSame(['Kelas Kurang'], array_column($panel['incomplete_rooms'], 'name'));
    }

    public function test_migration_marks_rooms_used_as_stock_location(): void
    {
        $migration = require database_path('migrations/2026_11_05_000008_add_is_storage_to_rooms.php');
        $used = $this->room();
        $free = $this->room();
        Consumable::factory()->create(['room_id' => $used->id]);

        $migration->down();
        $migration->up();

        $this->assertTrue((bool) DB::table('rooms')->where('id', $used->id)->value('is_storage'));
        $this->assertFalse((bool) DB::table('rooms')->where('id', $free->id)->value('is_storage'));
    }
}
