<?php

namespace App\Http\Requests\Concerns;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Validation\Rule;

/**
 * Pilihan status dari kondisi (aturan A6, SPEC §5): hanya Rusak Ringan yang boleh memilih
 * Tersedia/Perbaikan; kondisi lain statusnya otomatis (AssetCondition::resolveStatus()).
 */
trait ValidatesStatusChoice
{
    /**
     * @return array<string, array<mixed>>
     */
    protected function statusChoiceRules(): array
    {
        return [
            'status' => [
                'nullable',
                Rule::in([AssetStatus::Tersedia->value, AssetStatus::Perbaikan->value]),
                Rule::prohibitedIf(fn () => $this->input('condition') !== AssetCondition::RusakRingan->value),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function statusChoiceMessages(): array
    {
        return [
            'status.prohibited' => 'Status hanya bisa dipilih jika kondisi Rusak Ringan.',
            'status.in' => 'Status harus Tersedia atau Perbaikan.',
        ];
    }
}
