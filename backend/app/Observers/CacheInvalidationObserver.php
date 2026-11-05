<?php

namespace App\Observers;

use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Consumable;
use App\Models\ConsumableCategory;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RequestCatalog;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\SupplyRequest;
use App\Support\AppCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;

/**
 * Kosongkan grup cache yang memakai data model ini setiap kali model disimpan/dihapus. Dijalankan setelah
 * commit agar request lain tidak sempat mengisi cache dengan data sebelum transaksi selesai.
 */
class CacheInvalidationObserver implements ShouldHandleEventsAfterCommit
{
    /**
     * Model => grup cache yang bergantung padanya.
     *
     * @var array<class-string<Model>, list<string>>
     */
    public const GROUPS = [
        Asset::class => [AppCache::GROUP_OPS],
        Loan::class => [AppCache::GROUP_OPS],
        Consumable::class => [AppCache::GROUP_OPS],
        SupplyRequest::class => [AppCache::GROUP_OPS],
        RoomStandard::class => [AppCache::GROUP_OPS],
        Room::class => [AppCache::GROUP_MASTER, AppCache::GROUP_OPS],
        Building::class => [AppCache::GROUP_MASTER, AppCache::GROUP_OPS],
        Department::class => [AppCache::GROUP_MASTER, AppCache::GROUP_OPS],
        RequestCatalog::class => [AppCache::GROUP_MASTER, AppCache::GROUP_OPS],
        ConsumableCategory::class => [AppCache::GROUP_MASTER, AppCache::GROUP_OPS],
        AssetType::class => [AppCache::GROUP_MASTER],
    ];

    public function saved(Model $model): void
    {
        $this->flush($model);
    }

    public function deleted(Model $model): void
    {
        $this->flush($model);
    }

    private function flush(Model $model): void
    {
        AppCache::flush(...self::GROUPS[$model::class] ?? []);
    }
}
