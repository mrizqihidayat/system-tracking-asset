<?php

namespace App\Models;

use Database\Factories\DepartmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'is_internal', 'is_active'])]
class Department extends Model
{
    /** @use HasFactory<DepartmentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_internal' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Asset, $this>
     */
    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class, 'source_department_id');
    }

    /**
     * @return HasMany<RequestCatalog, $this>
     */
    public function catalogItems(): HasMany
    {
        return $this->hasMany(RequestCatalog::class, 'provider_department_id');
    }

    /**
     * @return HasMany<Consumable, $this>
     */
    public function consumables(): HasMany
    {
        return $this->hasMany(Consumable::class, 'provider_department_id');
    }

    /**
     * @return HasMany<SupplyRequest, $this>
     */
    public function supplyRequests(): HasMany
    {
        return $this->hasMany(SupplyRequest::class, 'provider_department_id');
    }
}
