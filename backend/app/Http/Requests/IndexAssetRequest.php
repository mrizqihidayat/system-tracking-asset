<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAssetRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Filter daftar aset (SPEC §5); aset Tidak Layak Pakai tetap tampil (bisa difilter lewat status).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['nullable', 'integer'],
            'building_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'source_department_id' => ['nullable', 'integer'],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
