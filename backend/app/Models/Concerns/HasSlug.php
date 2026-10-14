<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Slug untuk URL halaman frontend: dibuat sekali dari nama saat data dibuat dan tidak
 * berubah saat nama diganti. Bentrok diberi angka (gudang-2). Kolom slug tidak fillable.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model) {
            $model->slug = $model->uniqueSlug();
        });
    }

    /**
     * Batas keunikan slug (default: seluruh tabel).
     *
     * @param  Builder<static>  $query
     * @return Builder<static>
     */
    protected function slugScope(Builder $query): Builder
    {
        return $query;
    }

    protected function slugFallback(): string
    {
        return Str::snake(class_basename($this));
    }

    private function uniqueSlug(): string
    {
        $base = Str::slug((string) $this->name) ?: $this->slugFallback();
        $slug = $base;

        for ($suffix = 2; $this->slugScope(static::query())->where('slug', $slug)->exists(); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
