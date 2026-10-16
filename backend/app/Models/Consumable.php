<?php

namespace App\Models;

use App\Enums\ConsumableStatus;
use App\Support\Search;
use Database\Factories\ConsumableFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'category', 'unit', 'quantity', 'min_quantity', 'provider_department_id', 'catalog_id', 'room_id', 'note'])]
class Consumable extends Model
{
    /** @use HasFactory<ConsumableFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'quantity' => 0,
        'min_quantity' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'min_quantity' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function providerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'provider_department_id');
    }

    /**
     * @return BelongsTo<RequestCatalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(RequestCatalog::class, 'catalog_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<RoomStandard, $this>
     */
    public function roomStandards(): HasMany
    {
        return $this->hasMany(RoomStandard::class);
    }

    /**
     * Filter daftar stok (SPEC §10); dipakai daftar & laporan.
     *
     * @param  Builder<Consumable>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->withStatus(ConsumableStatus::from($status)))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereRaw('lower(category) = ?', [mb_strtolower($category)]))
            ->when($filters['provider_department_id'] ?? null, fn ($query, $id) => $query->where('provider_department_id', $id))
            ->when($filters['room_id'] ?? null, fn ($query, $id) => $query->where('room_id', $id))
            ->when($filters['building_id'] ?? null, fn ($query, $id) => $query->whereHas('room', fn ($room) => $room->where('building_id', $id)));

        Search::apply($query, $filters['search'] ?? null, ['consumables.name', 'consumables.category']);
    }

    public function status(): ConsumableStatus
    {
        return ConsumableStatus::fromQuantities($this->quantity, $this->min_quantity);
    }

    /**
     * Filter status (rumus sama dengan status(); dipakai ulang T14 & T15).
     *
     * @param  Builder<Consumable>  $query
     */
    #[Scope]
    protected function withStatus(Builder $query, ConsumableStatus $status): void
    {
        match ($status) {
            ConsumableStatus::Habis => $query->where('quantity', '<=', 0),
            ConsumableStatus::Menipis => $query->where('quantity', '>', 0)->whereColumn('quantity', '<=', 'min_quantity'),
            ConsumableStatus::Aman => $query->whereColumn('quantity', '>', 'min_quantity')->where('quantity', '>', 0),
        };
    }
}
