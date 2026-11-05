<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\RoomFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['building_id', 'name', 'is_storage', 'last_checked_at', 'last_checked_by'])]
class Room extends Model
{
    /** @use HasFactory<RoomFactory> */
    use HasFactory, HasSlug;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_storage' => false,
    ];

    /**
     * Slug ruangan unik per gedung.
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function slugScope(Builder $query): Builder
    {
        return $query->where('building_id', $this->building_id);
    }

    protected function slugFallback(): string
    {
        return 'ruangan';
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_storage' => 'boolean',
            'last_checked_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Building, $this>
     */
    public function building(): BelongsTo
    {
        return $this->belongsTo(Building::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function lastCheckedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_checked_by');
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }

    /**
     * Barang stok yang lokasi simpannya ruangan ini.
     *
     * @return HasMany<Consumable, $this>
     */
    public function storedConsumables(): HasMany
    {
        return $this->hasMany(Consumable::class);
    }

    /**
     * @return HasMany<RoomStandard, $this>
     */
    public function standards(): HasMany
    {
        return $this->hasMany(RoomStandard::class);
    }
}
