<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Sort server, cache data master/dashboard + invalidasi lewat observer, paginasi riwayat aset.
 */
class ListPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function queryCount(string $uri): int
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->getJson($uri)->assertOk();

        return count(DB::getQueryLog());
    }

    public function test_list_endpoints_sort_by_allowed_column_both_directions(): void
    {
        foreach (['Bravo', 'Alpha', 'Charlie'] as $name) {
            Asset::factory()->create(['name' => $name]);
        }

        $this->getJson('/api/assets?sort=name')->assertOk()->assertJsonPath('data.0.name', 'Alpha');
        $this->getJson('/api/assets?sort=-name')->assertOk()->assertJsonPath('data.0.name', 'Charlie');

        Consumable::factory()->create(['name' => 'Spidol', 'quantity' => 9]);
        Consumable::factory()->create(['name' => 'Kertas', 'quantity' => 2]);

        $this->getJson('/api/consumables?sort=quantity')->assertOk()->assertJsonPath('data.0.name', 'Kertas');
        $this->getJson('/api/consumables?sort=-quantity')->assertOk()->assertJsonPath('data.0.name', 'Spidol');
    }

    public function test_asset_list_uses_two_queries_regardless_of_row_count(): void
    {
        Asset::factory()->count(5)->create();

        $this->assertSame(2, $this->queryCount('/api/assets'));
        $this->assertSame(2, $this->queryCount('/api/assets?search=shd&status=Tersedia&sort=-name'));
    }

    public function test_asset_list_returns_only_table_columns(): void
    {
        $room = Room::factory()->for(Building::factory()->create(['name' => 'Gedung A']))->create(['name' => 'Ruang 1']);
        $type = AssetType::factory()->for(Category::factory()->create(['name' => 'Elektronik']))->create(['name' => 'Proyektor']);
        $source = Department::factory()->create(['is_internal' => false]);
        $asset = Asset::factory()->create([
            'asset_type_id' => $type->id,
            'room_id' => $room->id,
            'source_department_id' => $source->id,
        ]);

        $row = $this->getJson('/api/assets')
            ->assertOk()
            ->assertJsonPath('data.0.category_name', 'Elektronik')
            ->assertJsonPath('data.0.asset_type_name', 'Proyektor')
            ->assertJsonPath('data.0.room_name', 'Ruang 1')
            ->assertJsonPath('data.0.building_name', 'Gedung A')
            ->assertJsonPath('data.0.source_name', $source->name)
            ->assertJsonPath('data.0.source_is_internal', false)
            ->json('data.0');

        $this->assertSame(
            ['id', 'code', 'name', 'category_name', 'asset_type_name', 'room_name', 'building_name', 'source_name', 'source_is_internal', 'condition', 'status', 'active_loan_due_date'],
            array_keys($row),
        );
        $this->assertNull($row['active_loan_due_date']);

        // T45 (UAT A5): aset yang sedang dipinjam membawa rencana kembali untuk alasan "Sedang dipinjam sampai …".
        $asset->update(['status' => AssetStatus::Dipinjam]);
        Loan::factory()->create(['asset_id' => $asset->id, 'due_date' => '2026-11-20']);
        $this->getJson('/api/assets')->assertOk()->assertJsonPath('data.0.active_loan_due_date', '2026-11-20');

        $this->getJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.room.name', 'Ruang 1')
            ->assertJsonStructure(['data' => ['current_location', 'active_loan', 'brand'], 'meta' => ['actions']]);
    }

    public function test_unknown_sort_is_rejected(): void
    {
        $this->getJson('/api/assets?sort=public_token')->assertUnprocessable()->assertJsonValidationErrors('sort');
        $this->getJson('/api/loans?sort=-password')->assertUnprocessable()->assertJsonValidationErrors('sort');
    }

    public function test_list_response_keeps_paginator_shape(): void
    {
        $this->getJson('/api/supply-requests?sort=-request_date&per_page=5')
            ->assertOk()
            ->assertJsonStructure(['data', 'links', 'meta' => ['current_page', 'last_page', 'per_page', 'total']])
            ->assertJsonPath('meta.per_page', 5);
    }

    public function test_master_list_is_cached_and_invalidated_on_change(): void
    {
        ConsumableCategory::factory()->create(['name' => 'Elektronik']);

        $this->getJson('/api/consumable-categories')->assertOk()->assertJsonCount(1, 'data');

        // Panggilan kedua dari cache: tidak ada query ke tabel kategori.
        $this->queryCount('/api/consumable-categories');
        $this->assertSame(0, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'categories'))->count());

        $this->postJson('/api/consumable-categories', ['name' => 'Furnitur'])->assertCreated();

        $this->getJson('/api/consumable-categories')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_cached_lists_keep_nested_relations_when_store_serializes(): void
    {
        // Seperti store file/database: nilai di-serialize dan dibaca ulang dengan serializable_classes = false.
        config(['cache.stores.array.serialize' => true, 'cache.serializable_classes' => false]);
        Cache::purge('array');

        $room = Room::factory()->create();
        $provider = Department::factory()->create(['name' => 'SGA']);
        RequestCatalog::factory()->create(['provider_department_id' => $provider->id]);

        foreach ([1, 2] as $call) {
            $this->getJson('/api/rooms')->assertOk()->assertJsonPath('data.0.building.name', $room->building->name);
            $this->getJson('/api/request-catalog')->assertOk()->assertJsonPath('data.0.provider_department.name', 'SGA');
            $this->getJson('/api/dashboard/panels')->assertOk();
        }
    }

    public function test_master_search_is_not_served_from_cache(): void
    {
        ConsumableCategory::factory()->create(['name' => 'Elektronik']);
        ConsumableCategory::factory()->create(['name' => 'Furnitur']);

        $this->getJson('/api/consumable-categories')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/consumable-categories?search=furn')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_dashboard_cache_is_invalidated_when_asset_changes(): void
    {
        $asset = Asset::factory()->create();

        $this->getJson('/api/dashboard/cards')->assertOk()->assertJsonPath('data.needs_repair.assets_in_repair', 0);

        $asset->update(['status' => AssetStatus::Perbaikan]);

        $this->getJson('/api/dashboard/cards')->assertOk()->assertJsonPath('data.needs_repair.assets_in_repair', 1);
    }

    public function test_room_readiness_cache_is_invalidated_when_room_is_added(): void
    {
        $room = Room::factory()->create();

        $this->getJson('/api/room-readiness')->assertOk()->assertJsonCount(1, 'data');

        $this->postJson("/api/buildings/{$room->building_id}/rooms", ['name' => 'Ruang Baru'])->assertCreated();

        $this->getJson('/api/room-readiness')->assertOk()->assertJsonPath('data.0.rooms_count', 2);
    }

    public function test_asset_events_are_paginated(): void
    {
        $asset = Asset::factory()->create();
        $room = Room::factory()->create();

        $this->getJson("/api/assets/{$asset->id}/events?per_page=1")
            ->assertOk()
            ->assertJsonStructure(['data', 'meta' => ['current_page', 'last_page', 'total']]);

        $this->patchJson("/api/assets/{$asset->id}/location", ['room_id' => $room->id])->assertOk();

        $this->getJson("/api/assets/{$asset->id}/events?per_page=1")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.event_type', 'Pindah Lokasi');
    }
}
