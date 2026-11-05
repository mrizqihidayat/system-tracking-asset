<?php

namespace App\Models;

use Database\Factories\ConsumableCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class ConsumableCategory extends Model
{
    /** @use HasFactory<ConsumableCategoryFactory> */
    use HasFactory;

    /**
     * @return HasMany<Consumable, $this>
     */
    public function consumables(): HasMany
    {
        return $this->hasMany(Consumable::class);
    }
}
