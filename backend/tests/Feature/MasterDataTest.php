<?php

namespace Tests\Feature;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\SupplyRequest;
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
            'consumable categories' => ['GET', '/api/consumable-categories'],
            'asset types' => ['GET', '/api/asset-types'],
            'store asset type' => ['POST', '/api/asset-types'],
            'borrower units' => ['GET', '/api/lookups/borrower-units'],
            'request methods' => ['GET', '/api/lookups/request-methods'],
        ];
    }

    private function categoryId(): int
    {
        return Category::query()->value('id') ?? Category::factory()->create()->id;
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
        $this->postJson('/api/buildings', [])
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

    public function test_store_asset_type_generates_code_when_empty(): void
    {
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Laptop'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LPT')
            ->assertJsonPath('data.category_id', $this->categoryId())
            ->assertJsonPath('data.category.name', Category::query()->value('name'));

        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Laptop Gaming'])
            ->assertCreated()
            ->assertJsonPath('data.code', 'LG'.'M');
    }

    public function test_store_asset_type_accepts_manual_code_uppercased(): void
    {
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Proyektor', 'code' => ' prj '])
            ->assertCreated()
            ->assertJsonPath('data.code', 'PRJ');
    }

    public function test_store_asset_type_validates_code(): void
    {
        AssetType::factory()->create(['code' => 'PRJ']);

        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Proyektor', 'code' => 'prj'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Kode sudah digunakan.']);

        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Kamera', 'code' => 'K-1'])
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

    public function test_asset_types_search(): void
    {
        AssetType::factory()->create(['name' => 'Laptop']);
        AssetType::factory()->create(['name' => 'Kursi']);

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

    public function test_slug_follows_rename_and_cannot_be_mass_assigned(): void
    {
        $building = Building::factory()->create(['name' => 'Gedung IHS', 'slug' => 'custom']);
        $this->assertSame('gedung-ihs', $building->slug);

        $building->update(['name' => 'Gedung IHS Baru', 'slug' => 'lain']);
        $this->assertSame('gedung-ihs-baru', $building->fresh()->slug);

        // Nama berubah tanpa mengubah slug-nya: slug tetap, tidak diberi angka.
        $building->update(['name' => 'GEDUNG IHS BARU']);
        $this->assertSame('gedung-ihs-baru', $building->fresh()->slug);

        // Ubah kolom lain: slug tidak dibuat ulang walau ada data lain yang kini bernama sama.
        $other = Building::factory()->create(['name' => 'Gedung X']);
        $other->update(['updated_at' => now()->addMinute()]);
        $this->assertSame('gedung-x', $other->fresh()->slug);

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

    public function test_type_code_used_by_other_type_is_rejected_but_own_old_code_is_allowed(): void
    {
        $projector = AssetType::factory()->create(['name' => 'Proyektor', 'code' => 'PRO']);
        Asset::factory()->for($projector)->create(['code' => 'SHD-PRJ-001']);
        $this->postJson('/api/asset-types', ['category_id' => $this->categoryId(), 'name' => 'Printer', 'code' => 'PRJ'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Kode pernah dipakai jenis lain.']);

        $other = AssetType::factory()->create(['code' => 'PRT']);
        $this->putJson("/api/asset-types/{$other->id}", ['code' => 'PRJ'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Kode pernah dipakai jenis lain.']);

        $this->putJson("/api/asset-types/{$projector->id}", ['code' => 'PRJ'])
            ->assertOk()
            ->assertJsonPath('data.code', 'PRJ');
    }

    public function test_type_code_is_free_again_after_assets_are_deleted(): void
    {
        $old = AssetType::factory()->create(['code' => 'OLD']);
        $asset = Asset::factory()->for($old)->create(['code' => 'SHD-FLP-003']);
        $payload = ['name' => 'Flipchart', 'code' => 'FLP'];

        $this->postJson('/api/asset-types', [...$payload, 'category_id' => $this->categoryId()])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Kode pernah dipakai jenis lain.']);

        $asset->delete();

        $this->postJson('/api/asset-types', [...$payload, 'category_id' => $this->categoryId()])->assertCreated();
    }

    public function test_last_number_cannot_be_set_through_api(): void
    {
        $type = AssetType::factory()->create(['code' => 'PRJ']);

        $this->putJson("/api/asset-types/{$type->id}", ['name' => 'Proyektor', 'last_number' => 50])->assertOk();

        $this->assertSame(0, $type->fresh()->last_number);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function lookupProvider(): array
    {
        return [
            'unit peminjam' => ['/api/lookups/borrower-units'],
            'cara permintaan' => ['/api/lookups/request-methods'],
        ];
    }

    #[DataProvider('lookupProvider')]
    public function test_lookup_search_must_be_text_up_to_100_characters(string $endpoint): void
    {
        foreach (['search[]=x', 'search[a]=b', 'search='.str_repeat('a', 101)] as $query) {
            $this->getJson("{$endpoint}?{$query}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('search');
        }

        $this->getJson("{$endpoint}?search=a")->assertOk();
    }

    public function test_request_methods_lookup_returns_fixed_options_only(): void
    {
        SupplyRequest::factory()->create(['request_method' => 'Telepon']);
        SupplyRequest::factory()->create(['request_method' => 'WA']);

        $this->getJson('/api/lookups/request-methods')
            ->assertOk()
            ->assertExactJson(['data' => ['Memo', 'Form', 'Email', 'WhatsApp', 'Lainnya']]);

        $this->getJson('/api/lookups/request-methods?search=tel')
            ->assertOk()
            ->assertExactJson(['data' => []]);

        $this->getJson('/api/lookups/request-methods?search=WHATS')
            ->assertOk()
            ->assertExactJson(['data' => ['WhatsApp']]);
    }

    public function test_request_method_accepts_free_text(): void
    {
        $request = SupplyRequest::factory()->create(['request_method' => 'Telepon Ext. 1234']);

        $this->assertSame('Telepon Ext. 1234', $request->fresh()->request_method);
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

    public function test_department_update_and_delete(): void
    {
        $unit = Department::factory()->create(['name' => 'SGA']);
        Department::factory()->create(['name' => 'SIT']);

        $this->putJson("/api/departments/{$unit->id}", ['name' => 'sit'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->putJson("/api/departments/{$unit->id}", ['name' => 'SGA Baru'])->assertOk()->assertJsonPath('data.name', 'SGA Baru');
        $this->putJson("/api/departments/{$unit->id}", ['name' => 'SGA Baru'])->assertOk();

        $this->deleteJson("/api/departments/{$unit->id}")->assertOk();
        $this->assertModelMissing($unit);
    }

    public function test_internal_department_cannot_be_changed_or_deleted(): void
    {
        $shd = Department::factory()->internal()->create(['name' => 'SHD']);

        $this->putJson("/api/departments/{$shd->id}", ['name' => 'Lain'])->assertUnprocessable();
        $this->deleteJson("/api/departments/{$shd->id}")->assertUnprocessable();
        $this->assertModelExists($shd);
    }

    public function test_used_department_cannot_be_deleted(): void
    {
        $unit = Department::factory()->create();
        RequestCatalog::factory()->create(['provider_department_id' => $unit->id]);

        $this->deleteJson("/api/departments/{$unit->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('id');
        $this->assertModelExists($unit);
    }

    public function test_building_update_and_delete_rules(): void
    {
        $building = Building::factory()->create(['name' => 'IHS']);
        $room = Room::factory()->create(['building_id' => $building->id]);

        $this->putJson("/api/buildings/{$building->id}", ['name' => 'IHS Baru'])
            ->assertOk()
            ->assertJsonPath('data.name', 'IHS Baru');

        $this->deleteJson("/api/buildings/{$building->id}")->assertUnprocessable();

        $room->delete();
        $this->deleteJson("/api/buildings/{$building->id}")->assertOk();
        $this->assertModelMissing($building);
    }

    public function test_room_rename_updates_slug_and_name_is_unique_per_building(): void
    {
        $building = Building::factory()->create();
        $room = Room::factory()->create(['building_id' => $building->id, 'name' => 'Ruang A']);
        Room::factory()->create(['building_id' => $building->id, 'name' => 'Ruang B']);
        Room::factory()->create(['name' => 'Ruang C']);

        $this->putJson("/api/rooms/{$room->id}", ['name' => 'ruang b'])->assertUnprocessable();
        // Ruang C di gedung lain tidak bentrok: slug unik per gedung.
        $this->putJson("/api/rooms/{$room->id}", ['name' => 'Ruang C'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Ruang C')
            ->assertJsonPath('data.slug', 'ruang-c');
        // "Ruang B!" lolos validasi nama, tapi slug-nya bentrok dengan Ruang B → diberi angka.
        $this->putJson("/api/rooms/{$room->id}", ['name' => 'Ruang B!'])
            ->assertOk()
            ->assertJsonPath('data.slug', 'ruang-b-2');
    }

    public function test_room_in_use_cannot_be_deleted(): void
    {
        $room = Room::factory()->create();
        Asset::factory()->create(['room_id' => $room->id]);

        $this->deleteJson("/api/rooms/{$room->id}")->assertUnprocessable()->assertJsonValidationErrors('id');
        $this->assertModelExists($room);

        $empty = Room::factory()->create();
        $this->deleteJson("/api/rooms/{$empty->id}")->assertOk();
        $this->assertModelMissing($empty);
    }

    public function test_consumable_category_crud_and_delete_rules(): void
    {
        $id = $this->postJson('/api/consumable-categories', ['name' => ' ATK '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'ATK')
            ->assertJsonPath('data.consumables_count', 0)
            ->json('data.id');

        $this->postJson('/api/consumable-categories', ['name' => 'atk'])->assertJsonValidationErrors(['name' => 'Nama sudah digunakan.']);
        $this->postJson('/api/consumable-categories', [])->assertJsonValidationErrors('name');

        $this->getJson('/api/consumable-categories')->assertOk()->assertJsonCount(1, 'data');

        $this->putJson("/api/consumable-categories/{$id}", ['name' => 'atk'])->assertOk()->assertJsonPath('data.name', 'atk');

        $consumable = Consumable::factory()->create(['consumable_category_id' => $id]);
        $this->getJson('/api/consumable-categories')->assertJsonPath('data.0.consumables_count', 1);

        // Ganti nama langsung terlihat di data stok; yang masih dipakai tidak bisa dihapus.
        $this->getJson("/api/consumables/{$consumable->id}")->assertJsonPath('data.category', 'atk');
        $this->deleteJson("/api/consumable-categories/{$id}")->assertUnprocessable();
        $this->assertDatabaseHas('consumable_categories', ['id' => $id]);

        $consumable->update(['consumable_category_id' => null]);
        $this->deleteJson("/api/consumable-categories/{$id}")->assertOk();
        $this->assertDatabaseMissing('consumable_categories', ['id' => $id]);
    }

    public function test_asset_type_delete_rules(): void
    {
        $type = AssetType::factory()->create();
        Asset::factory()->create(['asset_type_id' => $type->id]);

        $this->deleteJson("/api/asset-types/{$type->id}")->assertUnprocessable();
        $this->assertModelExists($type);

        $unused = AssetType::factory()->create();
        $this->deleteJson("/api/asset-types/{$unused->id}")->assertOk();
        $this->assertModelMissing($unused);
    }
}
