<?php

namespace App\Services;

use App\Enums\AssetStatus;
use App\Enums\ConsumableStatus;
use App\Http\Resources\ConsumableResource;
use App\Http\Resources\LoanResource;
use App\Http\Resources\SupplyRequestResource;
use App\Models\Asset;
use App\Models\Consumable;
use App\Models\Loan;
use App\Models\SupplyRequest;
use App\Support\AppCache;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Dashboard (SPEC §11), dipecah per kelompok widget agar bisa dimuat paralel: kartu, kesiapan ruangan,
 * dan panel daftar. Tiap kelompok di-cache (grup ops, dikosongkan observer saat data berubah); kunci
 * memuat tanggal hari ini karena jatuh tempo/terlambat/perlu servis bergantung tanggal.
 * Jumlah query tetap (count/group, tanpa N+1). Aturan memakai ulang scope & service task sebelumnya.
 */
class DashboardService
{
    public const PANEL_LIMIT = 10;

    public const RECENT_REQUESTS_LIMIT = 5;

    public function __construct(private RoomReadinessService $readiness) {}

    /**
     * @return array<string, mixed>
     */
    public function cards(): array
    {
        return $this->remember('cards', fn () => [
            'total_assets' => $this->totalAssets(),
            'active_loans' => $this->activeLoans(),
            'needs_repair' => [
                'assets_in_repair' => Asset::query()->where('status', AssetStatus::Perbaikan->value)->count(),
            ],
            'incomplete_rooms' => $this->incompleteRooms($this->readiness->summary()),
            'needs_service' => $this->needsService(),
            'low_stock' => $this->lowStockCounts(),
        ]);
    }

    /**
     * Panel kesiapan ruangan per gedung (dari ringkasan kesiapan yang di-cache).
     *
     * @return list<array<string, mixed>>
     */
    public function roomReadiness(): array
    {
        return array_map(function (array $building) {
            // Gudang tidak masuk hitungan kesiapan.
            $rooms = collect($building['rooms'])->where('is_storage', false);
            $incomplete = $rooms->where('shortage_items', '>', 0);

            return [
                'id' => $building['id'],
                'name' => $building['name'],
                'slug' => $building['slug'],
                'rooms_count' => $rooms->count(),
                'rooms_without_standards' => $rooms->where('total_items', 0)->count(),
                'incomplete_rooms_count' => $incomplete->count(),
                'shortage_items' => $rooms->sum('shortage_items'),
                'unchecked_items' => $rooms->sum('unchecked_items'),
                'is_complete' => $incomplete->isEmpty(),
                'incomplete_rooms' => $incomplete->map(fn (array $room) => [
                    'id' => $room['id'],
                    'name' => $room['name'],
                    'slug' => $room['slug'],
                    'shortage_items' => $room['shortage_items'],
                    'unchecked_items' => $room['unchecked_items'],
                ])->values()->all(),
            ];
        }, $this->readiness->summary());
    }

    /**
     * @return array<string, mixed>
     */
    public function panels(Request $request): array
    {
        return $this->remember('panels', fn () => [
            'loans_due' => LoanResource::collection($this->loansDue())->resolve($request),
            'recent_requests' => SupplyRequestResource::collection($this->recentRequests())->resolve($request),
            'assets_need_service' => $this->assetsNeedService(),
            'low_stock' => ConsumableResource::collection($this->lowStock())->resolve($request),
        ]);
    }

    /**
     * @param  Closure(): array<string, mixed>  $callback
     * @return array<string, mixed>
     */
    private function remember(string $key, Closure $callback): array
    {
        return AppCache::remember(AppCache::GROUP_OPS, "dashboard:{$key}:".today()->toDateString(), $callback);
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
     * @param  list<array<string, mixed>>  $buildings  ringkasan kesiapan (RoomReadinessService::summary())
     * @return array{incomplete: int, total_rooms: int, total_buildings: int, incomplete_buildings: int}
     */
    private function incompleteRooms(array $buildings): array
    {
        $rooms = collect($buildings)->flatMap(fn (array $building) => $building['rooms'])->where('is_storage', false);

        return [
            'incomplete' => $rooms->where('shortage_items', '>', 0)->count(),
            'total_rooms' => $rooms->count(),
            'total_buildings' => count($buildings),
            'incomplete_buildings' => collect($buildings)->where('incomplete_rooms_count', '>', 0)->count(),
        ];
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
            ->with(['providerDepartment', 'consumableCategory', 'room.building'])
            ->orderBy('quantity')
            ->orderBy('name')
            ->limit(self::PANEL_LIMIT)
            ->get();
    }
}
