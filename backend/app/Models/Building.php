<?php

namespace App\Models;

use App\Models\Concerns\HasSlug;
use Database\Factories\BuildingFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name'])]
class Building extends Model
{
    /** @use HasFactory<BuildingFactory> */
    use HasFactory, HasSlug;

    protected function slugFallback(): string
    {
        return 'gedung';
    }

    /**
     * @return HasMany<Room, $this>
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }
}
