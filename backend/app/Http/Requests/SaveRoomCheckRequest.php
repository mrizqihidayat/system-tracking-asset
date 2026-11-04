<?php

namespace App\Http\Requests;

use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Simpan hasil cek: jumlah Ada baris Manual (boleh kosong jika ruangan hanya punya baris QR).
 */
class SaveRoomCheckRequest extends FormRequest
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
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'integer', 'distinct'],
            'items.*.actual_quantity' => ['required', 'integer', 'min:0', 'max:'.DbInteger::MAX],
        ];
    }
}
