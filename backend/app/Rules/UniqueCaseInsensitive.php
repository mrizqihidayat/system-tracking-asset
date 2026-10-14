<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\DB;

/**
 * Unik tanpa membedakan huruf besar/kecil ("sga" = "SGA"), berlaku di PostgreSQL & SQLite.
 */
class UniqueCaseInsensitive implements ValidationRule
{
    /**
     * @param  array<string, mixed>  $where  kondisi tambahan, mis. ['building_id' => 1]
     */
    public function __construct(
        private string $table,
        private string $column = 'name',
        private ?int $ignoreId = null,
        private array $where = [],
    ) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value)) {
            return;
        }

        $exists = DB::table($this->table)
            ->whereRaw("lower({$this->column}) = ?", [mb_strtolower(trim($value))])
            ->when($this->ignoreId, fn ($query) => $query->where('id', '!=', $this->ignoreId))
            ->where($this->where)
            ->exists();

        if ($exists) {
            $fail('validation.unique')->translate();
        }
    }
}
