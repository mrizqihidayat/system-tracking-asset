<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;

class UpdateAssetRequest extends StoreAssetRequest
{
    /**
     * Kirim field yang berubah saja. Jenis boleh diganti (kode ikut diganti, SPEC §5).
     * Status, kondisi, kode, dan token tidak bisa diubah di sini.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = ['asset_type_id' => ['sometimes', 'required', 'integer', 'exists:asset_types,id']];

        foreach ($this->dataRules() as $field => $fieldRules) {
            $rules[$field] = ['sometimes', ...$fieldRules];
        }

        return [
            ...$rules,
            'status' => ['prohibited'],
            'condition' => ['prohibited'],
            'code' => ['prohibited'],
            'public_token' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'status.prohibited' => 'Status diubah lewat peminjaman, lapor, atau servis.',
            'condition.prohibited' => 'Kondisi diubah lewat lapor, servis, atau pengembalian.',
            'code.prohibited' => 'Kode dibuat otomatis.',
            'public_token.prohibited' => 'Token tidak bisa diubah.',
        ];
    }
}
