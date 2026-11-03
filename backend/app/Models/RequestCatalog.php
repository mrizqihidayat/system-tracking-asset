<?php

namespace App\Models;

use Database\Factories\RequestCatalogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Table('request_catalog')]
#[Fillable(['item_name', 'provider_department_id', 'must_return', 'lead_time_days', 'note'])]
class RequestCatalog extends Model
{
    /** @use HasFactory<RequestCatalogFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'must_return' => 'boolean',
            'lead_time_days' => 'integer',
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
     * @return HasMany<SupplyRequest, $this>
     */
    public function supplyRequests(): HasMany
    {
        return $this->hasMany(SupplyRequest::class, 'catalog_id');
    }

    /**
     * @return HasMany<Consumable, $this>
     */
    public function consumables(): HasMany
    {
        return $this->hasMany(Consumable::class, 'catalog_id');
    }

    /**
     * @return HasMany<RoomStandard, $this>
     */
    public function roomStandards(): HasMany
    {
        return $this->hasMany(RoomStandard::class, 'catalog_id');
    }
}
