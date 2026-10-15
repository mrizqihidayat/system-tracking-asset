<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Http\Requests\Concerns\ValidatesStatusChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReportAssetRequest extends FormRequest
{
    use ValidatesStatusChoice;

    public const TYPE_DAMAGE = 'rusak';

    public const TYPE_LOST = 'hilang';

    public const TYPE_UNFIT = 'tidak_layak_pakai';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Catat kerusakan (kondisi Rusak Ringan/Berat, status aturan A6), catat kehilangan,
     * atau catat tidak layak pakai. Catatan wajib untuk semua jenis.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $isDamage = $this->input('type') === self::TYPE_DAMAGE;

        return [
            'type' => ['required', Rule::in([self::TYPE_DAMAGE, self::TYPE_LOST, self::TYPE_UNFIT])],
            'condition' => [
                Rule::requiredIf($isDamage),
                Rule::prohibitedIf(! $isDamage),
                Rule::in([AssetCondition::RusakRingan->value, AssetCondition::RusakBerat->value]),
            ],
            ...$this->statusChoiceRules(),
            'note' => ['required', 'string', 'max:1000'],
            'party' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->statusChoiceMessages(),
            'type.in' => 'Jenis laporan harus rusak, hilang, atau tidak layak pakai.',
            'condition.in' => 'Kondisi harus Rusak Ringan atau Rusak Berat.',
            'condition.prohibited' => 'Kondisi hanya diisi untuk catat kerusakan.',
        ];
    }
}
