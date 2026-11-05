<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * GET /{master}/{id}/usage: popup pemakaian sebelum hapus di halaman Data Master (T26b).
 */
class MasterUsageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_usage_requires_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/buildings/1/usage')->assertUnauthorized();
    }

    public function test_unused_master_can_be_deleted(): void
    {
        $building = Building::factory()->create();

        $this->getJson("/api/buildings/{$building->id}/usage")
            ->assertOk()
            ->assertExactJson(['data' => ['items' => [], 'total' => 0, 'can_delete' => true, 'reason' => null]]);
    }

    public function test_room_usage_lists_only_used_tables(): void
    {
        $room = Room::factory()->create();
        Asset::factory()->count(2)->create(['room_id' => $room->id]);

        $this->getJson("/api/rooms/{$room->id}/usage")
            ->assertOk()
            ->assertJsonPath('data.items', [['label' => 'aset', 'count' => 2]])
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_department_usage_and_shd_is_locked(): void
    {
        $unit = Department::factory()->create();
        RequestCatalog::factory()->create(['provider_department_id' => $unit->id]);

        $this->getJson("/api/departments/{$unit->id}/usage")
            ->assertOk()
            ->assertJsonPath('data.items', [['label' => 'item katalog', 'count' => 1]])
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.reason', null);

        $shd = Department::factory()->internal()->create();

        $this->getJson("/api/departments/{$shd->id}/usage")
            ->assertOk()
            ->assertJsonPath('data.can_delete', false)
            ->assertJsonPath('data.reason', 'Departemen SHD tidak dapat dihapus.');
    }

    public function test_consumable_category_usage(): void
    {
        $category = ConsumableCategory::factory()->create();
        Consumable::factory()->create(['consumable_category_id' => $category->id]);

        $this->getJson("/api/consumable-categories/{$category->id}/usage")
            ->assertJsonPath('data.items', [['label' => 'barang habis pakai', 'count' => 1]])
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_asset_type_usage(): void
    {
        $type = AssetType::factory()->create();
        Asset::factory()->create(['asset_type_id' => $type->id]);

        $this->getJson("/api/asset-types/{$type->id}/usage")
            ->assertJsonPath('data.items.0', ['label' => 'aset', 'count' => 1])
            ->assertJsonPath('data.can_delete', false);
    }

    public function test_usage_matches_delete_rule(): void
    {
        $room = Room::factory()->create();

        $this->getJson("/api/rooms/{$room->id}/usage")->assertJsonPath('data.can_delete', true);
        $this->deleteJson("/api/rooms/{$room->id}")->assertOk();
        $this->getJson("/api/rooms/{$room->id}/usage")->assertNotFound();
    }
}
