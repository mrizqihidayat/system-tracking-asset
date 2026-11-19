<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\EventType;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use App\Models\AssetFile;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use App\Support\Search;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AssetTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private AssetType $projector;

    private AssetType $laptop;

    private Department $shd;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->staff = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->staff);

        $this->projector = AssetType::factory()->create(['name' => 'Proyektor', 'code' => 'PRJ']);
        $this->laptop = AssetType::factory()->create(['name' => 'Laptop', 'code' => 'LPT']);
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->room = Room::factory()->for(Building::factory()->create(['name' => 'Gedung IHS']))->create(['name' => 'Ruang Kelas 1']);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'asset_type_id' => $this->projector->id,
            'name' => 'Proyektor Epson EB-X51',
            'brand' => 'Epson',
            'model' => 'EB-X51',
            'source_department_id' => $this->shd->id,
            'room_id' => $this->room->id,
            'acquisition_date' => '2024-01-15',
            ...$overrides,
        ];
    }

    private function createAsset(array $overrides = []): Asset
    {
        $id = $this->postJson('/api/assets', $this->payload($overrides))->assertCreated()->json('data.id');

        return Asset::findOrFail($id);
    }

    // ---------- auth ----------

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function protectedEndpointProvider(): array
    {
        return [
            'index' => ['GET', '/api/assets'],
            'store' => ['POST', '/api/assets'],
            'show' => ['GET', '/api/assets/1'],
            'update' => ['PUT', '/api/assets/1'],
            'destroy' => ['DELETE', '/api/assets/1'],
            'events' => ['GET', '/api/assets/1/events'],
            'code preview' => ['GET', '/api/assets/1/code-preview'],
        ];
    }

    #[DataProvider('protectedEndpointProvider')]
    public function test_endpoints_require_token(string $method, string $uri): void
    {
        $this->app['auth']->forgetGuards();

        $this->json($method, $uri)->assertUnauthorized();
    }

    // ---------- tambah ----------

    public function test_store_generates_code_and_registration_event(): void
    {
        $response = $this->postJson('/api/assets', $this->payload())
            ->assertCreated()
            ->assertJsonPath('data.code', 'SHD-PRJ-001')
            ->assertJsonPath('data.condition', 'Baik')
            ->assertJsonPath('data.status', 'Tersedia')
            ->assertJsonPath('data.room.building.name', 'Gedung IHS')
            ->assertJsonPath('data.source_department.is_internal', true);

        $asset = Asset::findOrFail($response->json('data.id'));
        $this->assertSame($asset->publicUrl(), $response->json('data.public_url'));

        $event = $asset->events()->sole();
        $this->assertSame(EventType::Registrasi, $event->event_type);
        $this->assertSame($this->staff->id, $event->recorded_by);
        $this->assertSame($this->room->id, $event->to_room_id);

        $this->postJson('/api/assets', $this->payload())->assertJsonPath('data.code', 'SHD-PRJ-002');
    }

    /**
     * @return array<string, array{0: string, 1: ?string, 2: string}>
     */
    public static function conditionStatusProvider(): array
    {
        return [
            'baik' => ['Baik', null, 'Tersedia'],
            'rusak berat otomatis perbaikan' => ['Rusak Berat', null, 'Perbaikan'],
            'rusak ringan default tersedia' => ['Rusak Ringan', null, 'Tersedia'],
            'rusak ringan pilih perbaikan' => ['Rusak Ringan', 'Perbaikan', 'Perbaikan'],
            'rusak ringan pilih tersedia' => ['Rusak Ringan', 'Tersedia', 'Tersedia'],
        ];
    }

    #[DataProvider('conditionStatusProvider')]
    public function test_store_status_follows_condition(string $condition, ?string $status, string $expected): void
    {
        $this->postJson('/api/assets', $this->payload(['condition' => $condition, 'status' => $status]))
            ->assertCreated()
            ->assertJsonPath('data.condition', $condition)
            ->assertJsonPath('data.status', $expected);
    }

    public function test_store_rejects_status_choice_unless_rusak_ringan(): void
    {
        $this->postJson('/api/assets', $this->payload(['condition' => 'Baik', 'status' => 'Perbaikan']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Status hanya bisa dipilih jika kondisi Rusak Ringan.']);

        $this->postJson('/api/assets', $this->payload(['condition' => 'Rusak Ringan', 'status' => 'Dipinjam']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status' => 'Status harus Tersedia atau Perbaikan.']);
    }

    public function test_store_validates_required_fields_in_indonesian(): void
    {
        $this->postJson('/api/assets', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'asset_type_id' => 'Jenis aset wajib diisi.',
                'name' => 'Nama wajib diisi.',
                'source_department_id' => 'Sumber wajib diisi.',
                'room_id' => 'Ruangan wajib diisi.',
            ]);
    }

    // ---------- daftar ----------

    public function test_index_filters_and_paginates(): void
    {
        $otherRoom = Room::factory()->for(Building::factory()->create(['name' => 'Millennials Hub']))->create();
        $unit = Department::factory()->create(['name' => 'SIT']);
        $furniture = AssetType::factory()->create(['code' => 'KRS']);

        $this->createAsset();
        $this->createAsset(['asset_type_id' => $this->laptop->id, 'name' => 'Laptop Lenovo', 'serial_number' => 'SN-XYZ-99', 'room_id' => $otherRoom->id]);
        $this->createAsset(['asset_type_id' => $furniture->id, 'name' => 'Kursi', 'source_department_id' => $unit->id, 'condition' => 'Rusak Berat']);

        $this->getJson('/api/assets')->assertOk()->assertJsonCount(3, 'data')->assertJsonPath('meta.total', 3);
        $this->getJson("/api/assets?asset_type_id={$furniture->id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kursi');
        $this->getJson("/api/assets?building_id={$otherRoom->building_id}")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Laptop Lenovo');
        $this->getJson("/api/assets?room_id={$this->room->id}")->assertJsonCount(2, 'data');
        $this->getJson("/api/assets?source_department_id={$unit->id}")->assertJsonCount(1, 'data');
        $this->getJson('/api/assets?condition=Rusak Berat')->assertJsonCount(1, 'data');
        $this->getJson('/api/assets?status=Perbaikan')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Kursi');
        $this->getJson('/api/assets?search=xyz-99')->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Laptop Lenovo');
        $this->getJson('/api/assets?search=shd-prj')->assertJsonCount(1, 'data');
        $this->getJson('/api/assets?per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.last_page', 2);
        $this->getJson('/api/assets?status=Rusak')->assertUnprocessable()->assertJsonValidationErrors('status');
    }

    public function test_search_matches_brand_and_model(): void
    {
        $this->createAsset(['name' => 'Proyektor Ruang A', 'brand' => 'Epson', 'model' => 'EB-X51']);
        $this->createAsset(['name' => 'Laptop Dosen', 'brand' => 'Lenovo', 'model' => 'ThinkPad T14']);

        $this->getJson('/api/assets?search=thinkpad')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Laptop Dosen');
        $this->getJson('/api/assets?search=eb-x5')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Proyektor Ruang A');
        $this->getJson('/api/assets?search=lenovo')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'Laptop Dosen');
    }

    public function test_model_is_saved_returned_and_logged_on_edit(): void
    {
        $asset = $this->createAsset(['model' => 'EB-X51']);
        $this->assertSame('EB-X51', $asset->model);

        $this->putJson("/api/assets/{$asset->id}", ['model' => 'EB-X52', 'acquisition_date' => '2024-01-15'])
            ->assertOk()
            ->assertJsonPath('data.model', 'EB-X52');
        $this->assertStringContainsString('Model: EB-X51 → EB-X52', $asset->events()->latest('id')->first()->note);

        $this->postJson('/api/assets', $this->payload(['model' => str_repeat('a', 101)]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['model' => 'Model']);
    }

    public function test_search_treats_special_characters_as_plain_text(): void
    {
        $this->createAsset(['name' => 'Kabel 100% Tembaga']);
        $this->createAsset(['name' => 'Kabel_HDMI']);
        $this->createAsset(['name' => 'Awas! Panas']);
        $this->createAsset(['name' => 'Folder C:\\Data']);
        $this->createAsset(['name' => 'Meja']);

        foreach (['%' => 'Kabel 100% Tembaga', '_' => 'Kabel_HDMI', '!' => 'Awas! Panas', '\\' => 'Folder C:\\Data'] as $search => $name) {
            $this->getJson('/api/assets?search='.urlencode($search))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', $name);
        }

        $this->getJson('/api/assets?search=KABEL')->assertOk()->assertJsonCount(2, 'data');
    }

    /** Test berjalan di sqlite yang tidak mengalami HY093 PDO pgsql, jadi SQL-nya dijaga langsung. */
    public function test_search_sql_has_no_backslash(): void
    {
        $this->assertStringNotContainsString('\\', Search::likeSql('assets.name'));
    }

    public function test_index_shows_current_loan_location(): void
    {
        $asset = $this->createAsset();
        $hub = Room::factory()->for(Building::factory()->create(['name' => 'Millennials Hub']))->create(['name' => 'Auditorium']);
        Loan::factory()->for($asset)->create(['destination_room_id' => $hub->id, 'due_date' => today()->subDay(), 'borrow_date' => today()->subDays(3)]);
        $asset->update(['status' => AssetStatus::Dipinjam]);

        $this->getJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertJsonPath('data.room.name', 'Ruang Kelas 1')
            ->assertJsonPath('data.current_location.on_loan', true)
            ->assertJsonPath('data.current_location.room.name', 'Auditorium')
            ->assertJsonPath('data.current_location.room.building.name', 'Millennials Hub')
            ->assertJsonPath('data.active_loan.is_overdue', true);
    }

    // ---------- ubah ----------

    public function test_update_records_changes_and_location_move(): void
    {
        $asset = $this->createAsset();
        $newRoom = Room::factory()->create(['name' => 'Gudang']);

        $this->putJson("/api/assets/{$asset->id}", [
            'brand' => 'BenQ',
            'acquisition_date' => '2024-02-01',
            'specification' => 'Spesifikasi baru',
            'room_id' => $newRoom->id,
        ])
            ->assertOk()
            ->assertJsonPath('data.brand', 'BenQ')
            ->assertJsonPath('data.room.name', 'Gudang')
            ->assertJsonPath('meta.code_changed', null);

        $edit = $asset->events()->where('event_type', EventType::UbahData)->sole();
        $this->assertSame('Merek: Epson → BenQ; Spesifikasi diubah; Tanggal perolehan: 2024-01-15 → 2024-02-01', $edit->note);

        $move = $asset->events()->where('event_type', EventType::PindahLokasi)->sole();
        $this->assertSame($this->room->id, $move->from_room_id);
        $this->assertSame($newRoom->id, $move->to_room_id);
    }

    public function test_update_without_changes_records_nothing(): void
    {
        $asset = $this->createAsset();

        $this->putJson("/api/assets/{$asset->id}", ['brand' => 'Epson', 'acquisition_date' => '2024-01-15'])->assertOk();

        $this->assertSame(1, $asset->events()->count());
    }

    public function test_update_rejects_status_condition_and_code(): void
    {
        $asset = $this->createAsset();

        $this->putJson("/api/assets/{$asset->id}", ['status' => 'Hilang', 'condition' => 'Rusak Berat', 'code' => 'X', 'public_token' => 'abc'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['status', 'condition', 'code', 'public_token']);

        $this->putJson("/api/assets/{$asset->id}", ['name' => ''])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name' => 'Nama wajib diisi.']);
    }

    public function test_change_type_gives_new_code_and_keeps_old_code_searchable(): void
    {
        $asset = $this->createAsset();
        $this->createAsset(['asset_type_id' => $this->laptop->id, 'name' => 'Laptop Lama']);

        $this->getJson("/api/assets/{$asset->id}/code-preview?asset_type_id={$this->laptop->id}")
            ->assertOk()
            ->assertExactJson(['data' => ['current_code' => 'SHD-PRJ-001', 'new_code' => 'SHD-LPT-002', 'code_will_change' => true]]);

        $this->putJson("/api/assets/{$asset->id}", ['asset_type_id' => $this->laptop->id])
            ->assertOk()
            ->assertJsonPath('data.code', 'SHD-LPT-002')
            ->assertJsonPath('meta.code_changed', ['from' => 'SHD-PRJ-001', 'to' => 'SHD-LPT-002'])
            ->assertJsonPath('data.previous_codes.0.code', 'SHD-PRJ-001');

        $this->assertSame('Jenis: Proyektor → Laptop; Kode: SHD-PRJ-001 → SHD-LPT-002', $asset->events()->where('event_type', EventType::UbahData)->value('note'));
        $this->assertDatabaseHas('asset_code_histories', ['asset_id' => $asset->id, 'asset_type_id' => $this->projector->id, 'code' => 'SHD-PRJ-001']);

        $this->getJson('/api/assets?search=SHD-PRJ-001')->assertJsonCount(1, 'data')->assertJsonPath('data.0.code', 'SHD-LPT-002');

        // Nomor lama tidak dipakai ulang oleh proyektor baru.
        $this->postJson('/api/assets', $this->payload())->assertJsonPath('data.code', 'SHD-PRJ-002');
    }

    public function test_code_preview_for_same_type_does_not_change(): void
    {
        $asset = $this->createAsset();

        $this->getJson("/api/assets/{$asset->id}/code-preview?asset_type_id={$this->projector->id}")
            ->assertExactJson(['data' => ['current_code' => 'SHD-PRJ-001', 'new_code' => 'SHD-PRJ-001', 'code_will_change' => false]]);
    }

    public function test_old_code_in_history_blocks_type_code_reuse_but_not_for_owner_type(): void
    {
        $asset = $this->createAsset();
        $this->putJson("/api/assets/{$asset->id}", ['asset_type_id' => $this->laptop->id])->assertOk();
        $this->projector->update(['code' => 'PRO']);

        $printer = AssetType::factory()->create(['code' => 'PRT']);
        $this->putJson("/api/asset-types/{$printer->id}", ['code' => 'PRJ'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['code' => 'Kode pernah dipakai jenis lain.']);

        $this->putJson("/api/asset-types/{$this->projector->id}", ['code' => 'PRJ'])->assertOk();
    }

    // ---------- hapus aset (permanen) ----------

    public function test_delete_asset_without_loans_needs_no_confirm_code(): void
    {
        $asset = $this->createAsset();

        $this->getJson("/api/assets/{$asset->id}/delete-impact")
            ->assertOk()
            ->assertJsonPath('data.loans_count', 0)
            ->assertJsonPath('data.active_loan', null)
            ->assertJsonPath('data.events_count', 1)
            ->assertJsonPath('data.requires_confirm_code', false);

        $this->deleteJson("/api/assets/{$asset->id}")
            ->assertOk()
            ->assertExactJson(['message' => 'Aset SHD-PRJ-001 dihapus.']);

        $this->assertModelMissing($asset);
        $this->assertDatabaseMissing('asset_events', ['asset_id' => $asset->id]);
        $this->getJson("/api/assets/{$asset->id}")->assertNotFound();
        $this->getJson('/api/assets')->assertJsonCount(0, 'data');
    }

    public function test_delete_borrowed_asset_removes_all_children_after_confirm_code(): void
    {
        $asset = $this->createAsset();
        $this->putJson("/api/assets/{$asset->id}", ['asset_type_id' => $this->laptop->id])->assertOk();
        $asset->refresh();
        AssetFile::factory()->for($asset)->create(['path' => 'assets/foto.jpg']);
        Storage::fake(config('filesystems.asset_disk'));
        Storage::disk(config('filesystems.asset_disk'))->put('assets/foto.jpg', 'x');
        $this->postJson('/api/loans', [
            'asset_id' => $asset->id, 'borrower_name' => 'Andi', 'borrower_unit' => 'SGA',
            'purpose' => 'Rapat', 'due_date' => today()->addDay()->toDateString(),
        ])->assertCreated();
        RoomStandard::factory()->for($this->room)->create(['count_method' => CountMethod::Qr, 'asset_type_id' => $this->laptop->id, 'actual_quantity' => null,
        ]);

        $this->getJson("/api/assets/{$asset->id}/delete-impact")
            ->assertOk()
            ->assertJsonPath('data.loans_count', 1)
            ->assertJsonPath('data.active_loan', ['borrower_name' => 'Andi', 'borrower_unit' => 'SGA'])
            ->assertJsonPath('data.files_count', 1)
            ->assertJsonPath('data.requires_confirm_code', true)
            ->assertJsonPath('data.affected_room_standards.0.room', 'Ruang Kelas 1')
            ->assertJsonPath('data.affected_room_standards.0.item_name', 'Laptop');

        $this->deleteJson("/api/assets/{$asset->id}")
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirm_code' => 'Kode konfirmasi tidak cocok.']);

        $this->deleteJson("/api/assets/{$asset->id}", ['confirm_code' => 'SHD-PRJ-001'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['confirm_code' => 'Kode konfirmasi tidak cocok.']);

        $this->assertModelExists($asset);

        $this->deleteJson("/api/assets/{$asset->id}", ['confirm_code' => ' shd-lpt-001 '])->assertOk();

        $this->assertModelMissing($asset);
        foreach (['loans', 'asset_events', 'asset_files', 'asset_code_histories'] as $table) {
            $this->assertDatabaseMissing($table, ['asset_id' => $asset->id]);
        }
        Storage::disk(config('filesystems.asset_disk'))->assertMissing('assets/foto.jpg');
        $this->getJson("/api/public/assets/{$asset->public_token}")->assertNotFound()->assertExactJson(['message' => 'Aset tidak ditemukan.']);
    }

    public function test_deleted_number_is_not_reused(): void
    {
        $asset = $this->createAsset();
        $this->deleteJson("/api/assets/{$asset->id}")->assertOk();

        $this->postJson('/api/assets', $this->payload())->assertJsonPath('data.code', 'SHD-PRJ-002');
    }

    // ---------- riwayat ----------

    public function test_events_are_listed_newest_first_with_recorder_and_rooms(): void
    {
        $asset = $this->createAsset();
        $newRoom = Room::factory()->create(['name' => 'Gudang']);
        $this->travel(1)->minutes();
        $this->putJson("/api/assets/{$asset->id}", ['room_id' => $newRoom->id])->assertOk();

        $this->getJson("/api/assets/{$asset->id}/events")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.event_type', 'Pindah Lokasi')
            ->assertJsonPath('data.0.from_room.name', 'Ruang Kelas 1')
            ->assertJsonPath('data.0.to_room.name', 'Gudang')
            ->assertJsonPath('data.0.recorded_by.name', 'Staf SHD')
            ->assertJsonPath('data.1.event_type', 'Registrasi');
    }

    public function test_missing_asset_returns_404(): void
    {
        $this->getJson('/api/assets/999')->assertNotFound()->assertExactJson(['message' => 'Data tidak ditemukan.']);
    }

    public function test_resolve_status_rule(): void
    {
        $this->assertSame(AssetStatus::Tersedia, AssetCondition::Baik->resolveStatus(AssetStatus::Perbaikan));
        $this->assertSame(AssetStatus::Perbaikan, AssetCondition::RusakBerat->resolveStatus(AssetStatus::Tersedia));
        $this->assertSame(AssetStatus::Tersedia, AssetCondition::RusakRingan->resolveStatus());
        $this->assertSame(AssetStatus::Perbaikan, AssetCondition::RusakRingan->resolveStatus(AssetStatus::Perbaikan));
        $this->assertTrue(AssetCodeHistory::query()->doesntExist());
    }
}
