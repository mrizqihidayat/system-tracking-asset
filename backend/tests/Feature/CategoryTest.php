<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kategori aset (SPEC §4): kategori → jenis, setiap jenis wajib punya kategori.
 */
class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    public function test_crud_with_case_insensitive_unique_name_and_type_count(): void
    {
        $id = $this->postJson('/api/categories', ['name' => 'Elektronik'])
            ->assertCreated()
            ->assertJsonPath('data.asset_types_count', 0)
            ->json('data.id');

        $this->postJson('/api/categories', ['name' => 'elektronik'])->assertJsonValidationErrors('name');
        $this->postJson('/api/categories', ['name' => ''])->assertJsonValidationErrors('name');

        AssetType::factory()->create(['category_id' => $id]);
        Category::factory()->create(['name' => 'Furnitur']);

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Elektronik')
            ->assertJsonPath('data.0.asset_types_count', 1)
            ->assertJsonPath('data.1.name', 'Furnitur');

        $this->putJson("/api/categories/{$id}", ['name' => 'Elektronika'])->assertOk()->assertJsonPath('data.name', 'Elektronika');
        $this->putJson("/api/categories/{$id}", ['name' => 'furnitur'])->assertJsonValidationErrors('name');
    }

    public function test_category_in_use_cannot_be_deleted(): void
    {
        $category = Category::factory()->create(['name' => 'Elektronik']);
        AssetType::factory()->count(2)->create(['category_id' => $category->id]);

        $this->getJson("/api/categories/{$category->id}/usage")
            ->assertOk()
            ->assertJsonPath('data.items.0', ['label' => 'jenis aset', 'count' => 2])
            ->assertJsonPath('data.can_delete', false);

        $this->deleteJson("/api/categories/{$category->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['id' => 'Kategori masih dipakai: 2 jenis aset.']);

        $unused = Category::factory()->create();
        $this->getJson("/api/categories/{$unused->id}/usage")->assertJsonPath('data.can_delete', true);
        $this->deleteJson("/api/categories/{$unused->id}")->assertOk();
        $this->assertModelMissing($unused);
    }

    public function test_asset_type_requires_category_and_can_change_category_and_code(): void
    {
        $this->postJson('/api/asset-types', ['name' => 'Laptop'])
            ->assertJsonValidationErrors(['category_id' => 'Kategori wajib diisi.']);
        $this->postJson('/api/asset-types', ['name' => 'Laptop', 'category_id' => 999])->assertJsonValidationErrors('category_id');

        $electronics = Category::factory()->create(['name' => 'Elektronik']);
        $training = Category::factory()->create(['name' => 'Alat Pelatihan']);

        $id = $this->postJson('/api/asset-types', ['name' => 'Flipchart', 'category_id' => $electronics->id])
            ->assertCreated()
            ->assertJsonPath('data.category.name', 'Elektronik')
            ->json('data.id');

        $this->putJson("/api/asset-types/{$id}", ['category_id' => $training->id, 'code' => 'FLP'])
            ->assertOk()
            ->assertJsonPath('data.category_id', $training->id)
            ->assertJsonPath('data.category.name', 'Alat Pelatihan')
            ->assertJsonPath('data.code', 'FLP');

        $this->getJson('/api/asset-types')->assertJsonPath('data.0.category.name', 'Alat Pelatihan');
    }

    public function test_asset_list_filters_by_category_and_detail_shows_category(): void
    {
        $electronics = AssetType::factory()->for(Category::factory()->create(['name' => 'Elektronik']))->create();
        $furniture = AssetType::factory()->for(Category::factory()->create(['name' => 'Furnitur']))->create();
        $projector = Asset::factory()->create(['asset_type_id' => $electronics->id]);
        Asset::factory()->create(['asset_type_id' => $furniture->id]);

        $this->getJson("/api/assets?category_id={$electronics->category_id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $projector->id)
            ->assertJsonPath('data.0.category_name', 'Elektronik');

        $this->getJson("/api/assets/{$projector->id}")->assertJsonPath('data.asset_type.category.name', 'Elektronik');
    }
}
