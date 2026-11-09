<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Slug untuk URL halaman frontend: dibuat dari nama saat data dibuat dan dibuat ulang saat
 * nama diganti (tautan lama tidak berlaku lagi). Bentrok diberi angka (gudang-2). Kolom slug tidak fillable.
 */
trait HasSlug
{
    public static function bootHasSlug(): void
    {
        static::creating(function (self $model) {
            $model->slug = $model->uniqueSlug();
        });

        static::updating(function (self $model) {
            if ($model->isDirty('name')) {
                $model->slug = $model->uniqueSlug();
            }
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

        // Data sendiri dikecualikan: ganti nama yang slug-nya sama (mis. hanya beda huruf besar) tidak diberi angka.
        $taken = fn (string $candidate) => $this->slugScope(static::query())
            ->when($this->exists, fn (Builder $query) => $query->whereKeyNot($this->getKey()))
            ->where('slug', $candidate)
            ->exists();

        for ($suffix = 2; $taken($slug); $suffix++) {
            $slug = "{$base}-{$suffix}";
        }

        return $slug;
    }
}
