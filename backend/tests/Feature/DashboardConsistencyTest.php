<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T45 (temuan UAT U5): setiap angka kartu Dashboard harus sama dengan hitungan di endpoint halaman tujuannya,
 * dan dihitung dari data di database (bukan nilai tetap).
 */
class DashboardConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private Department $shd;

    private Department $other;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->other = Department::factory()->create(['name' => 'SGA']);
    }

    /** Data campuran: semua status aset, sumber berbeda, pinjaman, stok, ruangan biasa dan gudang. */
    private function seedMixedData(): void
    {
        $ihs = Building::factory()->create(['name' => 'Gedung IHS']);
        $hub = Building::factory()->create(['name' => 'Millennials Hub']);
        $kelas = Room::factory()->create(['building_id' => $ihs->id, 'name' => 'Ruang Kelas 1']);
        Room::factory()->create(['building_id' => $ihs->id, 'name' => 'Ruang Rapat']);
        Room::factory()->create(['building_id' => $ihs->id, 'name' => 'Gudang IHS', 'is_storage' => true]);
        Room::factory()->create(['building_id' => $hub->id, 'name' => 'Auditorium']);
        $gudangHub = Room::factory()->create(['building_id' => $hub->id, 'name' => 'Gudang MH', 'is_storage' => true]);

        $asset = fn (array $overrides = []) => Asset::factory()->create([
            'source_department_id' => $this->shd->id, 'room_id' => $kelas->id, ...$overrides,
        ]);

        $asset();
        $asset(['source_department_id' => $this->other->id]);
        $asset(['status' => AssetStatus::Perbaikan, 'condition' => AssetCondition::RusakRingan]);
        $asset(['status' => AssetStatus::Perbaikan, 'condition' => AssetCondition::RusakBerat, 'source_department_id' => $this->other->id]);
        $asset(['status' => AssetStatus::Hilang]);
        $asset(['status' => AssetStatus::TidakLayakPakai]);
        $asset(['status' => AssetStatus::TidakLayakPakai, 'source_department_id' => $this->other->id]);

        Loan::factory()->create(['asset_id' => $asset(['status' => AssetStatus::Dipinjam])->id, 'due_date' => today()->subDays(2)]);
        Loan::factory()->create(['asset_id' => $asset(['status' => AssetStatus::Dipinjam])->id, 'due_date' => today()]);
        Loan::factory()->create(['asset_id' => $asset(['status' => AssetStatus::Dipinjam])->id, 'due_date' => today()->addDays(4)]);
        Loan::factory()->create(['asset_id' => $asset()->id, 'status' => LoanStatus::Dikembalikan, 'due_date' => today()->subDays(9)]);

        Consumable::factory()->create(['name' => 'Habis', 'quantity' => 0, 'min_quantity' => 5, 'provider_department_id' => $this->other->id, 'room_id' => $gudangHub->id]);
        Consumable::factory()->create(['name' => 'Menipis', 'quantity' => 3, 'min_quantity' => 5, 'provider_department_id' => $this->other->id, 'room_id' => $gudangHub->id]);
        Consumable::factory()->create(['name' => 'Aman', 'quantity' => 30, 'min_quantity' => 5, 'provider_department_id' => $this->other->id, 'room_id' => $gudangHub->id]);

        // Satu ruangan biasa kurang; satu gudang kurang (gudang tidak dinilai).
        RoomStandard::factory()->create(['room_id' => $kelas->id, 'standard_quantity' => 5, 'count_method' => CountMethod::Manual, 'actual_quantity' => 2]);
        RoomStandard::factory()->create(['room_id' => $gudangHub->id, 'standard_quantity' => 5, 'count_method' => CountMethod::Manual, 'actual_quantity' => 1]);
    }

    private function total(string $url): int
    {
        return $this->getJson($url)->assertOk()->json('meta.total');
    }

    public function test_asset_loan_and_stock_cards_match_their_list_pages(): void
    {
        $this->seedMixedData();
        $cards = $this->getJson('/api/dashboard/cards')->assertOk()->json('data');

        // Total aset = Daftar Aset tanpa filter (Tidak Layak Pakai ikut).
        $this->assertSame($this->total('/api/assets'), $cards['total_assets']['total']);
        $this->assertSame($this->total('/api/assets?status=Tidak Layak Pakai'), $cards['total_assets']['not_feasible']);
        $this->assertSame($this->total("/api/assets?source_department_id={$this->shd->id}"), $cards['total_assets']['owned_by_shd']);
        $this->assertSame($this->total("/api/assets?source_department_id={$this->other->id}"), $cards['total_assets']['from_other_units']);

        // Sedang dipinjam = Peminjaman (status Dipinjam), terlambat & jatuh tempo hari ini.
        $this->assertSame($this->total('/api/loans?status=Dipinjam'), $cards['active_loans']['total']);
        $this->assertSame($this->total('/api/loans?overdue=1'), $cards['active_loans']['overdue']);
        $this->assertSame($this->total('/api/loans?due_today=1'), $cards['active_loans']['due_today']);

        // Perlu perbaikan = Daftar Aset status Perbaikan (tujuan kartu), dirinci per kondisi.
        $this->assertSame($this->total('/api/assets?status=Perbaikan'), $cards['needs_repair']['assets_in_repair']);
        $this->assertSame($this->total('/api/assets?status=Perbaikan&condition=Rusak Ringan'), $cards['needs_repair']['light_damage']);
        $this->assertSame($this->total('/api/assets?status=Perbaikan&condition=Rusak Berat'), $cards['needs_repair']['heavy_damage']);

        // Stok menipis = Stok Habis Pakai status Menipis + Habis.
        $this->assertSame($this->total('/api/consumables?status=Menipis'), $cards['low_stock']['menipis']);
        $this->assertSame($this->total('/api/consumables?status=Habis'), $cards['low_stock']['habis']);
        $this->assertSame($cards['low_stock']['menipis'] + $cards['low_stock']['habis'], $cards['low_stock']['total']);

        // Dihitung dari database: angkanya tidak nol dan berubah saat data berubah.
        $this->assertSame(11, $cards['total_assets']['total']);
        Asset::factory()->create(['source_department_id' => $this->shd->id, 'room_id' => Room::query()->first()->id]);
        $this->assertSame(12, $this->getJson('/api/dashboard/cards')->json('data.total_assets.total'));
        $this->assertSame(12, $this->total('/api/assets'));
    }

    public function test_room_card_and_panel_match_room_readiness_page(): void
    {
        $this->seedMixedData();
        $cards = $this->getJson('/api/dashboard/cards')->assertOk()->json('data.incomplete_rooms');
        $panel = collect($this->getJson('/api/dashboard/room-readiness')->assertOk()->json('data'))->keyBy('id');
        $buildings = collect($this->getJson('/api/room-readiness')->assertOk()->json('data'));
        $rooms = $buildings->flatMap(fn (array $building) => $building['rooms']);

        // Kelengkapan Ruangan menampilkan 3 ruangan dinilai + 2 gudang; kartu memakai angka yang sama.
        $this->assertSame($rooms->where('is_storage', false)->count(), $cards['total_rooms']);
        $this->assertSame($rooms->where('is_storage', true)->count(), $cards['storage_rooms']);
        $this->assertSame([3, 2], [$cards['total_rooms'], $cards['storage_rooms']]);
        $this->assertSame($rooms->where('is_storage', false)->where('shortage_items', '>', 0)->count(), $cards['incomplete']);
        $this->assertSame(1, $cards['incomplete']);
        $this->assertSame($buildings->count(), $cards['total_buildings']);

        // Panel per gedung: ruangan dinilai + gudang = jumlah ruangan gedung itu di Kelengkapan Ruangan.
        foreach ($buildings as $building) {
            $this->assertSame(
                count($building['rooms']),
                $panel[$building['id']]['rooms_count'] + $panel[$building['id']]['storage_rooms_count'],
                "Jumlah ruangan {$building['name']} berbeda",
            );
        }
    }
}
