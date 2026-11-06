<?php

namespace App\Http\Requests;

use App\Rules\AssetTypeCodeNotUsedElsewhere;
use App\Rules\UniqueCaseInsensitive;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreAssetTypeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kode opsional: kosong = dibuat otomatis dari nama.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:100', new UniqueCaseInsensitive('asset_types', ignoreId: $this->ignoreId())],
            'code' => ['nullable', 'string', 'regex:/^[A-Z0-9]{2,5}$/', new UniqueCaseInsensitive('asset_types', 'code', $this->ignoreId()), new AssetTypeCodeNotUsedElsewhere($this->ignoreId())],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('code'))) {
            $this->merge(['code' => strtoupper(trim($this->input('code')))]);
        }
    }

    protected function ignoreId(): ?int
    {
        return null;
    }
}
