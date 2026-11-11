<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\LoanStatus;
use App\Enums\RequestStatus;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    private Department $shd;

    private Department $deptA;

    private Room $room;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create());
        $this->shd = Department::factory()->internal()->create(['name' => 'SHD']);
        $this->deptA = Department::factory()->create(['name' => 'Dept A']);
        $this->room = Room::factory()->create();
    }

    private function asset(array $overrides = []): Asset
    {
        return Asset::factory()->create([
            'source_department_id' => $this->shd->id,
            'room_id' => $this->room->id,
            ...$overrides,
        ]);
    }

    private const ENDPOINTS = ['/api/dashboard/cards', '/api/dashboard/room-readiness', '/api/dashboard/panels'];

    /**
     * Gabungan tiga endpoint dashboard dalam bentuk { cards, panels } (room_readiness masuk panels).
     */
    private function dashboard(): array
    {
        return [
            'cards' => $this->getJson('/api/dashboard/cards')->assertOk()->json('data'),
            'panels' => [
                'room_readiness' => $this->getJson('/api/dashboard/room-readiness')->assertOk()->json('data'),
                ...$this->getJson('/api/dashboard/panels')->assertOk()->json('data'),
            ],
        ];
    }

    public function test_requires_token(): void
    {
        $this->app['auth']->forgetGuards();

        foreach (self::ENDPOINTS as $endpoint) {
            $this->getJson($endpoint)->assertUnauthorized();
        }
    }

    public function test_empty_dashboard_has_all_cards_and_panels(): void
    {
        $data = $this->dashboard();

        $this->assertSame(['total_assets', 'active_loans', 'needs_repair', 'incomplete_rooms', 'needs_service', 'low_stock'], array_keys($data['cards']));
        $this->assertSame(['room_readiness', 'loans_due', 'handovers_today', 'recent_requests', 'assets_need_service', 'low_stock'], array_keys($data['panels']));
        $this->assertSame(['total' => 0, 'owned_by_shd' => 0, 'from_other_units' => 0, 'not_feasible' => 0], $data['cards']['total_assets']);
        $this->assertSame(1, $data['cards']['incomplete_rooms']['total_rooms']);
        $this->assertSame(0, $data['cards']['incomplete_rooms']['incomplete']);
        $this->assertSame([], $data['panels']['loans_due']);
    }

    public function test_total_assets_excludes_not_feasible_and_splits_by_source(): void
    {
        $this->asset();
        $this->asset(['status' => AssetStatus::Perbaikan]);
        $this->asset(['source_department_id' => $this->deptA->id]);
        $this->asset(['status' => AssetStatus::TidakLayakPakai]);
        $this->asset(['source_department_id' => $this->deptA->id, 'status' => AssetStatus::TidakLayakPakai]);

        $this->assertSame(
            ['total' => 3, 'owned_by_shd' => 2, 'from_other_units' => 1, 'not_feasible' => 2],
            $this->dashboard()['cards']['total_assets'],
        );
    }

    public function test_active_loans_and_due_panel(): void
    {
        $overdue = Loan::factory()->create(['asset_id' => $this->asset(['status' => AssetStatus::Dipinjam])->id, 'due_date' => today()->subDays(2)]);
        $dueToday = Loan::factory()->create(['asset_id' => $this->asset(['status' => AssetStatus::Dipinjam])->id, 'due_date' => today()]);
        Loan::factory()->create(['asset_id' => $this->asset(['status' => AssetStatus::Dipinjam])->id, 'due_date' => today()->addDays(5)]);
        Loan::factory()->create(['asset_id' => $this->asset()->id, 'status' => LoanStatus::Dikembalikan, 'due_date' => today()->subDays(9)]);

        $data = $this->dashboard();

        $this->assertSame(['total' => 3, 'due_today' => 1, 'overdue' => 1], $data['cards']['active_loans']);
        $this->assertSame([$overdue->id, $dueToday->id], array_column($data['panels']['loans_due'], 'id'));
        $this->assertTrue($data['panels']['loans_due'][0]['is_overdue']);
        $this->assertTrue($data['panels']['loans_due'][1]['is_due_today']);
    }

    public function test_needs_repair_counts_assets_in_repair_by_condition(): void
    {
        $this->asset(['status' => AssetStatus::Perbaikan, 'condition' => AssetCondition::RusakRingan]);
        $this->asset(['status' => AssetStatus::Perbaikan, 'condition' => AssetCondition::RusakBerat]);
        $this->asset(['status' => AssetStatus::Perbaikan, 'condition' => AssetCondition::RusakBerat]);
        $this->asset(['condition' => AssetCondition::RusakRingan]);

        $this->assertSame(
            ['assets_in_repair' => 3, 'light_damage' => 1, 'heavy_damage' => 2],
            $this->dashboard()['cards']['needs_repair'],
        );
    }

    public function test_incomplete_rooms_ignore_unchecked_and_rooms_without_standards(): void
    {
        $building = Building::factory()->create(['name' => 'IHS']);
        $short = Room::factory()->create(['building_id' => $building->id, 'name' => 'Kurang']);
        $unchecked = Room::factory()->create(['building_id' => $building->id, 'name' => 'Belum']);
        Room::factory()->create(['building_id' => $building->id, 'name' => 'Kosong']);

        RoomStandard::factory()->create([
            'room_id' => $short->id,
            'standard_quantity' => 5, 'count_method' => CountMethod::Manual, 'actual_quantity' => 2,
        ]);
        RoomStandard::factory()->create([
            'room_id' => $unchecked->id,
            'standard_quantity' => 5, 'count_method' => CountMethod::Manual, 'actual_quantity' => null,
        ]);

        $data = $this->dashboard();

        // 4 ruangan (1 dari setUp), 2 gedung (1 dari setUp); hanya "Kurang" yang tidak lengkap.
        $this->assertSame(
            ['incomplete' => 1, 'total_rooms' => 4, 'total_buildings' => 2, 'incomplete_buildings' => 1],
            $data['cards']['incomplete_rooms'],
        );

        $panel = collect($data['panels']['room_readiness'])->firstWhere('name', 'IHS');
        $this->assertSame(3, $panel['rooms_count']);
        $this->assertSame(1, $panel['rooms_without_standards']);
        $this->assertSame(1, $panel['incomplete_rooms_count']);
        $this->assertSame(1, $panel['shortage_items']);
        $this->assertSame(1, $panel['unchecked_items']);
        $this->assertFalse($panel['is_complete']);
        $this->assertSame('Kurang', $panel['incomplete_rooms'][0]['name']);
    }

    public function test_needs_service_window_and_excluded_statuses(): void
    {
        $late = $this->asset(['next_service_date' => today()->subDays(3)]);
        $soon = $this->asset(['next_service_date' => today()->addDays(14), 'status' => AssetStatus::Dipinjam]);
        $this->asset(['next_service_date' => today()->addDays(15)]);
        $this->asset(['next_service_date' => today()->subDays(3), 'status' => AssetStatus::Hilang]);
        $this->asset(['next_service_date' => today()->subDays(3), 'status' => AssetStatus::TidakLayakPakai]);
        $this->asset(['next_service_date' => null]);

        $data = $this->dashboard();

        $this->assertSame(['total' => 2, 'overdue' => 1, 'upcoming' => 1], $data['cards']['needs_service']);
        $this->assertSame([$late->id, $soon->id], array_column($data['panels']['assets_need_service'], 'id'));
        $this->assertSame(-3, $data['panels']['assets_need_service'][0]['days_until_service']);
        $this->assertTrue($data['panels']['assets_need_service'][0]['is_overdue']);
        $this->assertFalse($data['panels']['assets_need_service'][1]['is_overdue']);
    }

    public function test_low_stock_counts_menipis_and_habis(): void
    {
        $habis = Consumable::factory()->create(['name' => 'A', 'quantity' => 0, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        $menipis = Consumable::factory()->create(['name' => 'B', 'quantity' => 4, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);
        Consumable::factory()->create(['name' => 'C', 'quantity' => 50, 'min_quantity' => 10, 'provider_department_id' => $this->deptA->id]);

        $data = $this->dashboard();

        $this->assertSame(['total' => 2, 'menipis' => 1, 'habis' => 1], $data['cards']['low_stock']);
        $this->assertSame([$habis->id, $menipis->id], array_column($data['panels']['low_stock'], 'id'));
    }

    public function test_recent_requests_panel_shows_unresponded_status(): void
    {
        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $this->deptA->id, 'lead_time_days' => 2]);
        $creator = User::factory()->create();

        $old = SupplyRequest::factory()->create([
            'catalog_id' => $catalog->id, 'provider_department_id' => $this->deptA->id,
            'request_date' => today()->subDays(5), 'created_by' => $creator->id,
        ]);
        $new = SupplyRequest::factory()->create([
            'provider_department_id' => $this->deptA->id, 'request_date' => today(),
            'status' => RequestStatus::Diterima, 'created_by' => $creator->id,
        ]);

        $panel = $this->dashboard()['panels']['recent_requests'];

        $this->assertSame([$new->id, $old->id], array_column($panel, 'id'));
        $this->assertSame('Diterima', $panel[0]['display_status']);
        $this->assertSame('Belum direspons', $panel[1]['display_status']);
    }

    public function test_panels_are_limited(): void
    {
        foreach (range(1, 12) as $i) {
            $this->asset(['next_service_date' => today()->subDays($i)]);
            Consumable::factory()->create(['name' => "Barang {$i}", 'quantity' => 0, 'min_quantity' => 5, 'provider_department_id' => $this->deptA->id]);
        }

        $data = $this->dashboard();

        $this->assertSame(12, $data['cards']['needs_service']['total']);
        $this->assertCount(10, $data['panels']['assets_need_service']);
        $this->assertSame(12, $data['cards']['low_stock']['total']);
        $this->assertCount(10, $data['panels']['low_stock']);
    }

    public function test_query_count_does_not_grow_with_data(): void
    {
        $count = function (): int {
            DB::flushQueryLog();
            DB::enableQueryLog();
            foreach (self::ENDPOINTS as $endpoint) {
                $this->getJson($endpoint)->assertOk();
            }

            return count(DB::getQueryLog());
        };

        $catalog = RequestCatalog::factory()->create(['provider_department_id' => $this->deptA->id]);
        $seed = function (int $i) use ($catalog) {
            $asset = $this->asset(['status' => AssetStatus::Dipinjam, 'next_service_date' => today()->subDays($i), 'room_id' => Room::factory()->create()->id]);
            Loan::factory()->create(['asset_id' => $asset->id, 'due_date' => today()->subDays($i), 'destination_room_id' => Room::factory()->create()->id]);
            Consumable::factory()->create(['name' => "Stok {$i}", 'quantity' => 0, 'provider_department_id' => $this->deptA->id]);
            SupplyRequest::factory()->create(['catalog_id' => $catalog->id, 'provider_department_id' => $this->deptA->id, 'room_id' => Room::factory()->create()->id]);
            RoomStandard::factory()->create(['room_id' => Room::factory()->create()->id, 'count_method' => CountMethod::Qr, 'asset_type_id' => $asset->asset_type_id, 'actual_quantity' => null]);
        };

        $seed(1);
        $before = $count();

        foreach (range(2, 8) as $i) {
            $seed($i);
        }

        $after = $count();

        $this->assertSame($before, $after, "Query bertambah dari {$before} menjadi {$after}");
    }
}
