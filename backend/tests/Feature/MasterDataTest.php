<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MasterDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedEndpointProvider(): array
    {
        return [
            'departments' => ['GET', '/api/departments'],
            'buildings' => ['GET', '/api/buildings'],
            'building rooms' => ['GET', '/api/buildings/1/rooms'],
            'rooms' => ['GET', '/api/rooms'],
            'categories' => ['GET', '/api/categories'],
            'asset types' => ['GET', '/api/asset-types'],
            'store asset type' => ['POST', '/api/asset-types'],
            'borrower units' => ['GET', '/api/lookups/borrower-units'],
        ];
    }

    #[DataProvider('protectedEndpointProvider')]
    public function test_endpoints_require_token(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $uri)->assertUnauthorized();
    }

    public function test_departments_list_shd_first_and_support_search(): void
    {
        Department::factory()->create(['name' => 'SGA']);
        Department::factory()->create(['name' => 'SIT']);
        Department::factory()->internal()->create(['name' => 'SHD']);

        $this->getJson('/api/departments')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'SHD')
            ->assertJsonPath('data.0.is_internal', true)
            ->assertJsonCount(3, 'data');

        $this->getJson('/api/departments?search=gA')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'SGA');
    }

    public function test_store_department_is_never_internal(): void
    {
        $this->postJson('/api/departments', ['name' => 'SSO', 'is_internal' => true])
            ->assertCreated()
            ->assertJsonPath('data.name', 'SSO')
            ->assertJsonPath('data.is_internal', false);
    }

    public function test_duplicate_name_is_rejected_case_insensitively_with_indonesian_message(): void
    {
        Department::factory()->create(['name' => 'SGA']);

        $this->postJson('/api/departments', ['name' => ' sga '])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Nama sudah digunakan.']);
    }

    public function test_required_name_message_is_indonesian(): void
    {
        $this->postJson('/api/categories', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Nama wajib diisi.']);
    }

    public function test_buildings_list_with_room_count_and_store(): void
    {
        Room::factory()->count(2)->for(Building::factory()->create(['name' => 'Gedung IHS']))->create();

        $this->getJson('/api/buildings')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Gedung IHS')
            ->assertJsonPath('data.0.rooms_count', 2);

        $this->postJson('/api/buildings', ['name' => 'Millennials Hub'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Millennials Hub')
            ->assertJsonPath('data.rooms_count', 0);
    }

    public function test_rooms_of_building_and_store_room(): void
    {
        $building = Building::factory()->create();
        Room::factory()->for($building)->create(['name' => 'Ruang Rapat']);
        Room::factory()->create(['name' => 'Ruang Lain']);

        $this->getJson("/api/buildings/{$building->id}/rooms")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ruang Rapat');

        $this->postJson("/api/buildings/{$building->id}/rooms", ['name' => 'Gudang'])
            ->assertCreated()
            ->assertJsonPath('data.building_id', $building->id)
            ->assertJsonPath('data.building.name', $building->name);
    }

    public function test_room_name_is_unique_per_building_only(): void
    {
        $building = Building::factory()->create();
        Room::factory()->for($building)->create(['name' => 'Gudang']);
        $otherBuilding = Building::factory()->create();

        $this->postJson("/api/buildings/{$building->id}/rooms", ['name' => 'GUDANG'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');

        $this->postJson("/api/buildings/{$otherBuilding->id}/rooms", ['name' => 'Gudang'])
            ->assertCreated();
    }

    public function test_missing_building_returns_indonesian_404(): void
    {
        $this->getJson('/api/buildings/999/rooms')
            ->assertNotFound()
            ->assertExactJson(['message' => 'Data tidak ditemukan.']);
    }

    public function test_all_rooms_include_building_and_filter(): void
    {
        $ihs = Building::factory()->create(['name' => 'Gedung IHS']);
        $hub = Building::factory()->create(['name' => 'Millennials Hub']);
        Room::factory()->for($ihs)->create(['name' => 'Ruang Kelas 1']);
        Room::factory()->for($hub)->create(['name' => 'Auditorium']);

        $this->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.building.name', 'Gedung IHS');

        $this->getJson("/api/rooms?building_id={$hub->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Auditorium');

        $this->getJson('/api/rooms?search=kelas')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Ruang Kelas 1');
    }

    public function test_categories_list_with_type_count_and_store(): void
    {
        AssetType::factory()->count(2)->for(Category::factory()->create(['name' => 'Elektronik']))->create();

        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonPath('data.0.asset_types_count', 2);

        $this->postJson('/api/categories', ['name' => 'Furnitur'])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Furnitur');
    }

    public function test_store_asset_type_generates_code_when_empty(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/asset-types', ['category_id' => $category->id, 'name' => 'Laptop'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LPT')
            ->assertJsonPath('data.category.id', $category->id);

        $this->postJson('/api/asset-types', ['category_id' => $category->id, 'name' => 'Laptop Gaming'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LG'.'M');
    }

    public function test_store_asset_type_accepts_manual_code_uppercased(): void
    {
        $category = Category::factory()->create();

        $this->postJson('/api/asset-types', ['category_id' => $category->id, 'name' => 'Proyektor', 'code' => ' prj '])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PRJ');
    }

    public function test_store_asset_type_validates_code_and_category(): void
    {
        AssetType::factory()->create(['code' => 'PRJ']);

        $this->postJson('/api/asset-types', ['category_id' => 999, 'name' => 'Proyektor', 'code' => 'prj'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'category_id' => 'Kategori tidak ditemukan.',
                'code' => 'Kode sudah digunakan.',
            ]);

        $this->postJson('/api/asset-types', ['category_id' => Category::factory()->create()->id, 'name' => 'Kamera', 'code' => 'K-1'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Format kode tidak valid.']);
    }

    public function test_update_asset_type_code_keeps_existing_asset_codes(): void
    {
        $type = AssetType::factory()->create(['name' => 'Proyektor', 'code' => 'PRJ']);
        $asset = Asset::factory()->for($type)->create(['code' => 'SHD-PRJ-001']);

        $this->putJson("/api/asset-types/{$type->id}", ['code' => 'pro'])
            ->assertOk()
            ->assertJsonPath('data.code', 'PRO')
            ->assertJsonPath('data.name', 'Proyektor');

        $this->assertSame('SHD-PRJ-001', $asset->fresh()->code);
    }

    public function test_update_asset_type_allows_own_name_and_rejects_empty_values(): void
    {
        $type = AssetType::factory()->create(['name' => 'Proyektor', 'code' => 'PRJ']);

        $this->putJson("/api/asset-types/{$type->id}", ['name' => 'proyektor', 'code' => 'PRJ'])
            ->assertOk()
            ->assertJsonPath('data.name', 'proyektor');

        $this->putJson("/api/asset-types/{$type->id}", ['name' => '', 'code' => null])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'code']);
    }

    public function test_asset_types_filter_by_category_and_search(): void
    {
        $electronics = Category::factory()->create();
        AssetType::factory()->for($electronics)->create(['name' => 'Laptop']);
        AssetType::factory()->create(['name' => 'Kursi']);

        $this->getJson("/api/asset-types?category_id={$electronics->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Laptop');

        $this->getJson('/api/asset-types?search=KUR')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Kursi');
    }

    public function test_building_and_room_slugs_are_generated_and_returned(): void
    {
        $building = $this->postJson('/api/buildings', ['name' => 'Gedung IHS'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'gedung-ihs')
            ->json('data');

        $this->postJson("/api/buildings/{$building['id']}/rooms", ['name' => 'Ruang Kelas 1'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'ruang-kelas-1')
            ->assertJsonPath('data.building.slug', 'gedung-ihs');

        $this->getJson('/api/rooms')->assertJsonPath('data.0.slug', 'ruang-kelas-1');
        $this->getJson('/api/buildings')->assertJsonPath('data.0.slug', 'gedung-ihs');
    }

    public function test_slug_collision_gets_number_and_room_slug_is_unique_per_building(): void
    {
        $ihs = Building::factory()->create(['name' => 'Gedung IHS']);
        $this->assertSame('gedung-ihs-2', Building::factory()->create(['name' => 'Gedung IHS!'])->slug);

        $this->assertSame('gudang', Room::factory()->for($ihs)->create(['name' => 'Gudang'])->slug);
        $this->assertSame('gudang-2', Room::factory()->for($ihs)->create(['name' => 'Gudang!'])->slug);
        $this->assertSame('gudang', Room::factory()->create(['name' => 'Gudang'])->slug);
    }

    public function test_slug_does_not_change_on_rename_and_cannot_be_mass_assigned(): void
    {
        $building = Building::factory()->create(['name' => 'Gedung IHS', 'slug' => 'custom']);
        $this->assertSame('gedung-ihs', $building->slug);

        $building->update(['name' => 'Gedung IHS Baru', 'slug' => 'lain']);
        $this->assertSame('gedung-ihs', $building->fresh()->slug);

        $this->postJson('/api/buildings', ['name' => 'Millennials Hub', 'slug' => 'hub'])
            ->assertCreated()
            ->assertJsonPath('data.slug', 'millennials-hub');
    }

    public function test_api_still_uses_id_not_slug(): void
    {
        $building = Building::factory()->create(['name' => 'Gedung IHS']);

        $this->getJson("/api/buildings/{$building->id}/rooms")->assertOk();
        $this->getJson('/api/buildings/gedung-ihs/rooms')->assertNotFound();
    }

    public function test_borrower_units_come_from_departments(): void
    {
        Department::factory()->internal()->create(['name' => 'SHD']);
        Department::factory()->create(['name' => 'SGA']);
        Department::factory()->create(['name' => 'SIT']);

        $this->getJson('/api/lookups/borrower-units')
            ->assertOk()
            ->assertExactJson(['data' => ['SGA', 'SHD', 'SIT']]);

        $this->getJson('/api/lookups/borrower-units?search=s%')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->getJson('/api/lookups/borrower-units?search=it')
            ->assertOk()
            ->assertExactJson(['data' => ['SIT']]);
    }
}
