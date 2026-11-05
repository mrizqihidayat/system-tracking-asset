<?php

namespace App\Http\Requests;

use App\Enums\CountMethod;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\RequestCatalog;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Baris standar ruangan (SPEC §8); dipakai juga untuk PUT (definisi lengkap).
 * Tipe Aset = QR (jenis aset + sumber). Tipe Stok Habis Pakai = Manual + barang stok (sumber dari penyedianya).
 * Tipe Barang departemen lain = Manual tanpa barang stok (sumber bukan SHD, katalog opsional).
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
            'source_department_id' => ['nullable', 'integer', 'exists:departments,id', 'required_without:consumable_id'],
            'standard_quantity' => ['required', 'integer', 'min:1', 'max:'.DbInteger::MAX],
            'count_method' => ['required', Rule::enum(CountMethod::class)],
            'asset_type_id' => ['nullable', 'integer', 'exists:asset_types,id', 'required_if:count_method,'.CountMethod::Qr->value, 'prohibited_if:count_method,'.CountMethod::Manual->value],
            'consumable_id' => ['nullable', 'integer', 'exists:consumables,id', 'prohibited_if:count_method,'.CountMethod::Qr->value],
            'catalog_id' => ['nullable', 'integer', 'exists:request_catalog,id', 'prohibited_if:count_method,'.CountMethod::Qr->value],
            'fulfillment_note' => ['prohibited'],
            'actual_quantity' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'source_department_id.required_without' => 'Pilih sumber.',
            'asset_type_id.required_if' => 'Pilih jenis aset untuk tipe Aset.',
            'asset_type_id.prohibited_if' => 'Jenis aset hanya untuk tipe Aset.',
            'consumable_id.prohibited_if' => 'Barang stok hanya untuk tipe Stok Habis Pakai.',
            'catalog_id.prohibited_if' => 'Katalog hanya untuk tipe Barang departemen lain.',
            'fulfillment_note.prohibited' => 'Pemenuhan mengikuti data stok, tidak diisi sendiri.',
            'actual_quantity.prohibited' => 'Jumlah Ada diisi lewat simpan hasil cek.',
        ];
    }

    /**
     * Manual tanpa barang stok hanya untuk barang departemen lain; katalognya harus dari departemen yang sama.
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->input('count_method') !== CountMethod::Manual->value) {
                return;
            }

            if ($this->filled('consumable_id')) {
                if ($this->filled('catalog_id')) {
                    $validator->errors()->add('catalog_id', 'Katalog hanya untuk tipe Barang departemen lain.');
                }

                return;
            }

            if (Department::query()->whereKey($this->input('source_department_id'))->value('is_internal')) {
                $validator->errors()->add('consumable_id', 'Pilih barang dari Stok Habis Pakai.');

                return;
            }

            $provider = $this->filled('catalog_id') ? RequestCatalog::query()->whereKey($this->input('catalog_id'))->value('provider_department_id') : null;

            if ($provider !== null && (int) $provider !== (int) $this->input('source_department_id')) {
                $validator->errors()->add('catalog_id', 'Katalog harus dari departemen yang sama.');
            }
        }];
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
