<?php

namespace App\Http\Requests;

use App\Enums\CountMethod;
use App\Models\Consumable;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Baris standar ruangan (SPEC §8); dipakai juga untuk PUT (definisi lengkap).
 * Tipe Aset = QR (jenis aset + sumber), tipe Stok Habis Pakai = Manual (barang stok; sumber dari penyedianya).
 * Jumlah Ada baris Manual hanya lewat POST /rooms/{id}/check.
 */
class StoreRoomStandardRequest extends FormRequest
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
            'item_name' => ['required', 'string', 'max:150'],
            'source_department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_if:count_method,'.CountMethod::Qr->value],
            'standard_quantity' => ['required', 'integer', 'min:1'],
            'count_method' => ['required', Rule::enum(CountMethod::class)],
            'asset_type_id' => ['nullable', 'integer', 'exists:asset_types,id', 'required_if:count_method,'.CountMethod::Qr->value, 'prohibited_if:count_method,'.CountMethod::Manual->value],
            'consumable_id' => ['nullable', 'integer', 'exists:consumables,id', 'required_if:count_method,'.CountMethod::Manual->value, 'prohibited_if:count_method,'.CountMethod::Qr->value],
            'fulfillment_note' => ['prohibited'],
            'catalog_id' => ['prohibited'],
            'actual_quantity' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_department_id.required_if' => 'Pilih sumber untuk tipe Aset.',
            'asset_type_id.required_if' => 'Pilih jenis aset untuk tipe Aset.',
            'asset_type_id.prohibited_if' => 'Jenis aset hanya untuk tipe Aset.',
            'consumable_id.required_if' => 'Pilih barang dari Stok Habis Pakai.',
            'consumable_id.prohibited_if' => 'Barang stok hanya untuk tipe Stok Habis Pakai.',
            'fulfillment_note.prohibited' => 'Pemenuhan mengikuti data stok, tidak diisi sendiri.',
            'catalog_id.prohibited' => 'Pemenuhan mengikuti data stok, tidak diisi sendiri.',
            'actual_quantity.prohibited' => 'Jumlah Ada diisi lewat simpan hasil cek.',
        ];
    }

    /**
     * Data siap simpan: sumber baris stok selalu penyedia barangnya.
     *
     * @return array<string, mixed>
     */
    public function standardData(): array
    {
        $data = $this->validated();

        if (! empty($data['consumable_id'])) {
            $data['source_department_id'] = Consumable::query()->whereKey($data['consumable_id'])->value('provider_department_id');
        }

        return $data;
    }
}
