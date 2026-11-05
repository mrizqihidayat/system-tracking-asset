<?php

namespace Tests\Feature;

use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Models\Asset;
use App\Models\RoomStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tambahan khusus GET /assets/{id}: room_standard (hanya di detail) dan meta.actions.
 */
class AssetDetailExtrasTest extends TestCase
{
    use RefreshDatabase;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->asset = Asset::factory()->create();
    }

    public function test_room_standard_with_quantity_when_type_is_in_room_standard(): void
    {
        RoomStandard::factory()->for($this->asset->room)->create([
            'count_method' => CountMethod::Qr,
            'asset_type_id' => $this->asset->asset_type_id,
            'standard_quantity' => 3,
            'actual_quantity' => null,
        ]);

        $this->getJson("/api/assets/{$this->asset->id}")
            ->assertOk()
            ->assertJsonPath('data.room_standard', ['is_standard' => true, 'item_name' => $this->asset->assetType->name, 'quantity' => 3]);
    }

    public function test_room_standard_false_without_standard(): void
    {
        $this->getJson("/api/assets/{$this->asset->id}")
            ->assertOk()
            ->assertJsonPath('data.room_standard', ['is_standard' => false, 'item_name' => null, 'quantity' => null]);
    }

    public function test_manual_standard_row_is_not_counted(): void
    {
        RoomStandard::factory()->for($this->asset->room)->create([
            'count_method' => CountMethod::Manual,
            'asset_type_id' => null,
        ]);

        $this->getJson("/api/assets/{$this->asset->id}")
            ->assertJsonPath('data.room_standard.is_standard', false);
    }

    public function test_standard_in_another_room_is_not_counted(): void
    {
        RoomStandard::factory()->create([
            'count_method' => CountMethod::Qr,
            'asset_type_id' => $this->asset->asset_type_id,
            'actual_quantity' => null,
        ]);

        $this->getJson("/api/assets/{$this->asset->id}")
            ->assertJsonPath('data.room_standard.is_standard', false);
    }

    public function test_room_standard_is_not_in_list(): void
    {
        $this->getJson('/api/assets')
            ->assertOk()
            ->assertJsonMissingPath('data.0.room_standard');
    }

    public function test_detail_actions_follow_status(): void
    {
        $this->getJson("/api/assets/{$this->asset->id}")
            ->assertJsonPath('meta.actions', ['pinjam', 'pindah_lokasi', 'catat_kerusakan', 'catat_kehilangan', 'catat_tidak_layak_pakai']);

        $this->asset->update(['status' => AssetStatus::Hilang]);

        $this->getJson("/api/assets/{$this->asset->id}")
            ->assertJsonPath('meta.actions', ['tandai_ditemukan', 'pindah_lokasi']);
    }
}
