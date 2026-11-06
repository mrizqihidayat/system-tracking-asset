<?php

namespace App\Http\Requests;

use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Isi dari stok (SPEC §8): tanpa `quantity` = min(kekurangan, stok). Jumlah boleh melebihi kekurangan,
 * batasnya sisa stok (dicek di ConsumableService::record()).
 */
class FillFromStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'quantity' => ['nullable', 'integer', 'min:1', 'max:'.DbInteger::MAX],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
