<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Requests\Concerns\ValidatesListQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexAssetRequest extends FormRequest
{
    use ValidatesListQuery;

    public const DEFAULT_PER_PAGE = 15;

    /** Kunci sort => kolom (sort=kunci naik, sort=-kunci turun). */
    public const SORTABLE = ['code' => 'assets.code', 'name' => 'assets.name', 'status' => 'assets.status', 'condition' => 'assets.condition', 'next_service_date' => 'assets.next_service_date'];

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
            'asset_type_id' => ['nullable', 'integer'],
            'building_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'source_department_id' => ['nullable', 'integer'],
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
            'status' => ['nullable', Rule::enum(AssetStatus::class)],
            ...$this->listRules(),
        ];
    }
}
