<?php

namespace App\Http\Requests;

use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Buat beberapa permintaan barang stok sekaligus ke satu departemen penyedia (saran permintaan, SPEC §9).
 * Isian bersama berlaku untuk semua barang; jumlah per barang.
 */
class StoreSupplyRequestBatchRequest extends FormRequest
{
    public const MAX_ITEMS = 50;

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
            'provider_department_id' => ['required', 'integer', 'exists:departments,id'],
            'request_method' => ['required', 'string', 'max:50'],
            'request_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:'.self::MAX_ITEMS],
            'items.*.consumable_id' => ['required', 'integer', 'distinct', 'exists:consumables,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:'.DbInteger::MAX],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'items.required' => 'Pilih minimal satu barang.',
            'items.min' => 'Pilih minimal satu barang.',
            'items.*.consumable_id.distinct' => 'Barang yang sama tidak boleh muncul dua kali.',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['items.*.quantity' => 'jumlah', 'items.*.consumable_id' => 'barang'];
    }
}
