<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\ConsumableStatus;
use App\Enums\DamageConfirmation;
use App\Http\Resources\ConsumableResource;
use App\Http\Resources\LoanResource;
use App\Http\Resources\SupplyRequestResource;
use App\Models\Asset;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\Loan;
use App\Models\Room;
use App\Models\SupplyRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Dashboard (SPEC §11): semua kartu & panel dalam satu payload dengan jumlah query tetap
 * (count/group, tanpa N+1). Aturan memakai ulang scope & service task sebelumnya.
 */
class DashboardService
{
    public const PANEL_LIMIT = 10;

    public const RECENT_REQUESTS_LIMIT = 5;

    public function __construct(private RoomReadinessService $readiness) {}

    /**
     * @return array{cards: array<string, mixed>, panels: array<string, mixed>}
     */
    public function data(Request $request): array
    {
        $buildings = $this->readiness->readiness();
        $rooms = $this->roomSummaries($buildings);

        return [
            'cards' => [
                'total_assets' => $this->totalAssets(),
                'active_loans' => $this->activeLoans(),
                'needs_repair' => [
                    'assets_in_repair' => Asset::query()->where('status', AssetStatus::Perbaikan->value)->count(),
                    'loans_pending_confirmation' => Loan::query()->where('damage_confirmation', DamageConfirmation::MenungguKonfirmasi->value)->count(),
                ],
                'incomplete_rooms' => $this->incompleteRooms($buildings, $rooms),
                'needs_service' => $this->needsService(),
                'low_stock' => $this->lowStockCounts(),
            ],
            'panels' => [
                'loans_due' => LoanResource::collection($this->loansDue())->resolve($request),
                'room_readiness' => $this->roomReadinessPanel($buildings, $rooms),
                'recent_requests' => SupplyRequestResource::collection($this->recentRequests())->resolve($request),
                'assets_need_service' => $this->assetsNeedService(),
                'low_stock' => ConsumableResource::collection($this->lowStock())->resolve($request),
            ],
        ];
    }

    /**
     * Tidak Layak Pakai tidak dihitung; jumlahnya terpisah.
     *
     * @return array{total: int, owned_by_shd: int, from_other_units: int, not_feasible: int}
     */
    private function totalAssets(): array
    {
        $notFeasible = AssetStatus::TidakLayakPakai->value;

        $row = Asset::query()
            ->join('departments', 'departments.id', '=', 'assets.source_department_id')
            ->toBase()
            ->selectRaw('sum(case when assets.status <> ? then 1 else 0 end) as total', [$notFeasible])
            ->selectRaw('sum(case when assets.status <> ? and departments.is_internal then 1 else 0 end) as owned', [$notFeasible])
            ->selectRaw('sum(case when assets.status = ? then 1 else 0 end) as not_feasible', [$notFeasible])
            ->first();

        $total = (int) $row->total;
        $owned = (int) $row->owned;

        return [
            'total' => $total,
            'owned_by_shd' => $owned,
            'from_other_units' => $total - $owned,
            'not_feasible' => (int) $row->not_feasible,
        ];
    }

    /**
     * @return array{total: int, due_today: int, overdue: int}
     */
    private function activeLoans(): array
    {
        return [
            'total' => Loan::query()->where('status', 'Dipinjam')->count(),
            'due_today' => Loan::query()->dueToday()->count(),
            'overdue' => Loan::query()->overdue()->count(),
        ];
    }

    /**
     * Ringkasan per ruangan (id ruangan => ringkasan); ruangan tanpa standar = lengkap (tidak ada kekurangan).
     *
     * @param  Collection<int, Building>  $buildings
     * @return Collection<int, array{total_items: int, shortage_items: int, unchecked_items: int, is_complete: bool}>
     */
    private function roomSummaries(Collection $buildings): Collection
    {
        return $buildings
            ->flatMap(fn (Building $building) => $building->rooms)
            ->mapWithKeys(fn (Room $room) => [$room->id => $this->readiness->summarize($room->standards)]);
    }

    /**
     * @param  Collection<int, Building>  $buildings
     * @param  Collection<int, array<string, mixed>>  $rooms
     * @return array{incomplete: int, total_rooms: int, total_buildings: int, incomplete_buildings: int}
     */
    private function incompleteRooms(Collection $buildings, Collection $rooms): array
    {
        $incompleteBuildings = $buildings->filter(
            fn (Building $building) => $building->rooms->contains(fn (Room $room) => ! $rooms[$room->id]['is_complete'])
        )->count();

        return [
            'incomplete' => $rooms->where('is_complete', false)->count(),
            'total_rooms' => $rooms->count(),
            'total_buildings' => $buildings->count(),
            'incomplete_buildings' => $incompleteBuildings,
        ];
    }

    /**
     * @param  Collection<int, Building>  $buildings
     * @param  Collection<int, array<string, mixed>>  $rooms
     * @return list<array<string, mixed>>
     */
    private function roomReadinessPanel(Collection $buildings, Collection $rooms): array
    {
        return $buildings->map(function (Building $building) use ($rooms) {
            $summaries = $building->rooms->map(fn (Room $room) => ['room' => $room] + $rooms[$room->id]);

            return [
                'id' => $building->id,
                'name' => $building->name,
                'slug' => $building->slug,
                'rooms_count' => $summaries->count(),
                'rooms_without_standards' => $summaries->where('total_items', 0)->count(),
                'incomplete_rooms_count' => $summaries->where('is_complete', false)->count(),
                'shortage_items' => $summaries->sum('shortage_items'),
                'unchecked_items' => $summaries->sum('unchecked_items'),
                'is_complete' => $summaries->every(fn (array $row) => $row['is_complete']),
                'incomplete_rooms' => $summaries->where('is_complete', false)->map(fn (array $row) => [
                    'id' => $row['room']->id,
                    'name' => $row['room']->name,
                    'slug' => $row['room']->slug,
                    'shortage_items' => $row['shortage_items'],
                    'unchecked_items' => $row['unchecked_items'],
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /**
     * @return array{total: int, overdue: int, upcoming: int}
     */
    private function needsService(): array
    {
        $total = Asset::query()->needsService()->count();
        $overdue = Asset::query()->needsService()->whereDate('next_service_date', '<', today())->count();

        return ['total' => $total, 'overdue' => $overdue, 'upcoming' => $total - $overdue];
    }

    /**
     * @return array{total: int, menipis: int, habis: int}
     */
    private function lowStockCounts(): array
    {
        $menipis = Consumable::query()->withStatus(ConsumableStatus::Menipis)->count();
        $habis = Consumable::query()->withStatus(ConsumableStatus::Habis)->count();

        return ['total' => $menipis + $habis, 'menipis' => $menipis, 'habis' => $habis];
    }

    /**
     * Terlambat + jatuh tempo hari ini, tempo terdekat dulu.
     *
     * @return Collection<int, Loan>
     */
    private function loansDue(): Collection
    {
        return Loan::query()
            ->where('status', 'Dipinjam')
            ->whereDate('due_date', '<=', today())
            ->with(['asset', 'destinationRoom.building'])
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(self::PANEL_LIMIT)
            ->get();
    }

    /**
     * @return Collection<int, SupplyRequest>
     */
    private function recentRequests(): Collection
    {
        return SupplyRequest::query()
            ->with(SupplyRequestResource::RELATIONS)
            ->orderByDesc('request_date')
            ->orderByDesc('id')
            ->limit(self::RECENT_REQUESTS_LIMIT)
            ->get();
    }

    /**
     * Terlama/terlewat dulu.
     *
     * @return list<array<string, mixed>>
     */
    private function assetsNeedService(): array
    {
        return Asset::query()
            ->needsService()
            ->with('room.building')
            ->orderBy('next_service_date')
            ->orderBy('id')
            ->limit(self::PANEL_LIMIT)
            ->get()
            ->map(fn (Asset $asset) => [
                'id' => $asset->id,
                'code' => $asset->code,
                'name' => $asset->name,
                'status' => $asset->status->value,
                'next_service_date' => $asset->next_service_date->toDateString(),
                'days_until_service' => (int) today()->diffInDays($asset->next_service_date, false),
                'is_overdue' => $asset->next_service_date->lt(today()),
                'room' => $asset->room ? [
                    'id' => $asset->room->id,
                    'name' => $asset->room->name,
                    'building' => $asset->room->building->name,
                ] : null,
            ])
            ->all();
    }

    /**
     * Habis dulu, lalu paling sedikit.
     *
     * @return Collection<int, Consumable>
     */
    private function lowStock(): Collection
    {
        return Consumable::query()
            ->where(fn ($query) => $query->withStatus(ConsumableStatus::Habis)->orWhere(fn ($q) => $q->withStatus(ConsumableStatus::Menipis)))
            ->with(['providerDepartment', 'catalog', 'room.building'])
            ->orderBy('quantity')
            ->orderBy('name')
            ->limit(self::PANEL_LIMIT)
            ->get();
    }
}
