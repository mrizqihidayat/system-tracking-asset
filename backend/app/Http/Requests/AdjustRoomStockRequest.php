<?php

namespace App\Http\Requests;

use App\Enums\RoomStockTransactionType;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Catat perubahan satu barang habis pakai di ruangan (popup): jumlah sekarang hasil hitung staf.
 * Aturan yang bergantung saldo (alasan wajib saat berkurang, keterangan wajib saat bertambah) dicek di
 * RoomReadinessService::adjust() di dalam lock.
 */
class AdjustRoomStockRequest extends FormRequest
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
            'quantity' => ['required', 'integer', 'min:0', 'max:'.DbInteger::MAX],
            'reason' => ['nullable', 'string', Rule::in(array_map(fn (RoomStockTransactionType $type) => $type->value, RoomStockTransactionType::decreaseReasons()))],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.required' => 'Jumlah sekarang wajib diisi.',
            'quantity.min' => 'Jumlah tidak boleh negatif.',
            'reason.in' => 'Alasan tidak valid.',
            'note.max' => 'Keterangan maksimal 1000 karakter.',
        ];
    }
}
