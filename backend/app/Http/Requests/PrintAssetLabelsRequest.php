<?php

namespace App\Http\Requests;

use App\Services\AssetLabelService;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PrintAssetLabelsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Cetak label banyak aset: ids[] (maks. 120), aset harus ada.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.AssetLabelService::MAX_LABELS],
            'ids.*' => ['integer', 'distinct', 'exists:assets,id'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Pilih minimal satu aset.',
            'ids.max' => 'Maksimal :max label sekali cetak.',
            'ids.*.exists' => 'Aset tidak ditemukan.',
        ];
    }
}
