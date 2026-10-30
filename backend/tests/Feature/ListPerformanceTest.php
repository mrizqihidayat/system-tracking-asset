<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        Category::factory()->create(['name' => 'Elektronik']);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(1, 'data');

        // Panggilan kedua dari cache: tidak ada query ke tabel kategori.
        $this->queryCount('/api/categories');
        $this->assertSame(0, collect(DB::getQueryLog())->filter(fn ($q) => str_contains($q['query'], 'categories'))->count());

        $this->postJson('/api/categories', ['name' => 'Furnitur'])->assertCreated();

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_master_search_is_not_served_from_cache(): void
    {
        Category::factory()->create(['name' => 'Elektronik']);
        Category::factory()->create(['name' => 'Furnitur']);

        $this->getJson('/api/categories')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/categories?search=furn')->assertOk()->assertJsonCount(1, 'data');
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
