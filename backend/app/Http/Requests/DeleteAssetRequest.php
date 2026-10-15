<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class DeleteAssetRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Hapus aset permanen: confirm_code (= kode aset) wajib jika aset punya riwayat pinjam,
     * dicek di AssetService::delete().
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'confirm_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
