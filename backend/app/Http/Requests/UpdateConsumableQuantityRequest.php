<?php

namespace App\Http\Requests;

use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateConsumableQuantityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tepat salah satu: quantity (set langsung, ≥ 0) atau adjustment (tambah/kurang, ≠ 0).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:0', 'max:'.DbInteger::MAX, 'required_without:adjustment', 'prohibits:adjustment'],
            'adjustment' => ['nullable', 'integer', 'min:-'.DbInteger::MAX, 'max:'.DbInteger::MAX, 'not_in:0', 'required_without:quantity'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required_without' => 'Isi jumlah atau penyesuaian.',
            'adjustment.required_without' => 'Isi jumlah atau penyesuaian.',
            'quantity.prohibits' => 'Isi salah satu saja: jumlah atau penyesuaian.',
            'adjustment.not_in' => 'Penyesuaian tidak boleh 0.',
        ];
    }
}
