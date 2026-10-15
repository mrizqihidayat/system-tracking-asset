<?php

namespace App\Rules;

use App\Services\CodeGenerator;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Kode jenis yang dipakai di kode aset milik jenis lain (aset yang ada atau kode lama)
 * tidak boleh dipakai lagi, agar kode aset baru tidak bentrok dengan kode aset lama.
 */
class AssetTypeCodeNotUsedElsewhere implements ValidationRule
{
    public function __construct(private ?int $assetTypeId = null) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && app(CodeGenerator::class)->isTypeCodeUsedByOtherType($value, $this->assetTypeId)) {
            $fail('Kode pernah dipakai jenis lain.');
        }
    }
}
