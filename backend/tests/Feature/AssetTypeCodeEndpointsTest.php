<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Category;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Kode jenis otomatis dari nama & pratinjau kode aset untuk form aset.
 */
class AssetTypeCodeEndpointsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    private function categoryId(): int
    {
        return Category::query()->value('id') ?? Category::factory()->create()->id;
    }

    public function test_endpoints_require_token(): void
    {
        $this->app['auth']->forgetGuards();

        $this->getJson('/api/asset-types/1/next-code')->assertUnauthorized();
    }

    public function test_store_generates_code_from_name_and_skips_taken_codes(): void
    {
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Laptop'])->assertCreated()->assertJsonPath('data.code', 'LPT');
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Laptop Pro', 'code' => 'LPP'])->assertCreated();

        // Kode yang tersisa di kode aset milik jenis lain juga tidak dipakai.
        Asset::factory()->create(['code' => 'SHD-KRS-001', 'asset_type_id' => AssetType::factory()->create(['code' => 'MJA'])->id]);
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Kursi'])->assertCreated()->assertJsonPath('data.code', 'KRS2');
        $this->getJson('/api/asset-types/code-suggestion?name=Laptop')->assertStatus(405);
    }

    public function test_store_rejects_name_without_letters_when_code_is_empty(): void
    {
        foreach ([['name' => '123'], ['name' => '!!!', 'code' => '']] as $payload) {
            $this->postJson('/api/asset-types', [...$payload, 'category_id' => $this->categoryId()])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['code' => 'Kode singkat tidak dapat dibuat dari nama ini, isi kode secara manual.']);
        }

        $this->assertDatabaseCount('asset_types', 0);

        // Dengan kode manual, nama yang sama diterima.
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => '123', 'code' => 'ANG'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'ANG');
    }

    public function test_next_code_previews_without_using_the_number(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);
        $type->forceFill(['last_number' => 4])->save();

        $url = "/api/asset-types/{$type->id}/next-code";

        $this->getJson($url)->assertOk()->assertExactJson(['data' => ['next_code' => 'SHD-PRJ-005']]);
        $this->getJson($url)->assertJsonPath('data.next_code', 'SHD-PRJ-005');
        $this->assertSame(4, $type->fresh()->last_number);

        $this->getJson('/api/asset-types/999/next-code')->assertNotFound();
    }

    public function test_next_code_reads_latest_number_after_list_is_cached(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);
        $this->getJson('/api/asset-types')->assertOk();

        AssetType::query()->whereKey($type->id)->update(['last_number' => 7]);

        $this->getJson("/api/asset-types/{$type->id}/next-code")->assertJsonPath('data.next_code', 'SHD-PRJ-008');
    }
}
