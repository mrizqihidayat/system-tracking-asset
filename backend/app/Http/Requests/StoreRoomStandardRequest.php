<?php

namespace App\Http\Requests;

use App\Enums\CountMethod;
use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Baris standar ruangan (SPEC §8); dipakai juga untuk PUT (definisi lengkap).
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
            'source_department_id' => ['required', 'integer', 'exists:departments,id'],
            'standard_quantity' => ['required', 'integer', 'min:1'],
            'count_method' => ['required', Rule::enum(CountMethod::class)],
            'asset_type_id' => ['nullable', 'integer', 'exists:asset_types,id', 'required_if:count_method,'.CountMethod::Qr->value, 'prohibited_if:count_method,'.CountMethod::Manual->value],
            'consumable_id' => ['nullable', 'integer', 'exists:consumables,id', 'prohibited_if:count_method,'.CountMethod::Qr->value],
            'fulfillment_note' => ['nullable', 'string', 'max:255'],
            'catalog_id' => ['nullable', 'integer', 'exists:request_catalog,id'],
            'actual_quantity' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_type_id.required_if' => 'Pilih jenis aset untuk cara hitung QR.',
            'asset_type_id.prohibited_if' => 'Jenis aset hanya untuk cara hitung QR.',
            'consumable_id.prohibited_if' => 'Isi dari stok SHD hanya untuk cara hitung Manual.',
            'actual_quantity.prohibited' => 'Jumlah Ada diisi lewat simpan hasil cek.',
        ];
    }

    /**
     * Pemenuhan paling banyak satu; stok SHD & ambil dari hanya untuk sumber SHD, minta ke hanya untuk unit lain.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $filled = array_filter(['consumable_id', 'fulfillment_note', 'catalog_id'], fn ($field) => filled($this->input($field)));

            if (count($filled) > 1) {
                $validator->errors()->add('fulfillment', 'Pilih salah satu pemenuhan: stok SHD, ambil dari, atau minta ke.');

                return;
            }

            $isShd = (bool) Department::query()->whereKey($this->input('source_department_id'))->value('is_internal');
            $field = array_values($filled)[0] ?? null;

            if (in_array($field, ['consumable_id', 'fulfillment_note'], true) && ! $isShd) {
                $validator->errors()->add($field, 'Stok SHD atau ambil dari hanya untuk sumber SHD.');
            }

            if ($field === 'catalog_id' && $isShd) {
                $validator->errors()->add($field, 'Minta ke unit lain hanya untuk sumber unit lain.');
            }
        }];
    }
}
