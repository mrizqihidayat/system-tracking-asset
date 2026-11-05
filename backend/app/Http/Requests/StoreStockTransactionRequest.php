<?php

namespace App\Http\Requests;

use App\Enums\StockTransactionType;
use App\Models\StockTransaction;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Form transaksi stok: Barang Masuk, Pemakaian, Penghapusan memakai jumlah positif; Koreksi boleh + atau −.
 */
class StoreStockTransactionRequest extends FormRequest
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
        $min = $this->input('type') === StockTransactionType::Koreksi->value ? -DbInteger::MAX : 1;

        return [
            'type' => ['required', Rule::enum(StockTransactionType::class)],
            'quantity' => ['required', 'integer', "min:{$min}", 'max:'.DbInteger::MAX, 'not_in:0'],
            'reason' => ['nullable', 'required_if:type,'.StockTransactionType::Penghapusan->value, 'prohibited_unless:type,'.StockTransactionType::Penghapusan->value, Rule::in(StockTransaction::REASONS)],
            'note' => ['nullable', 'string', 'max:1000', 'required_if:type,'.StockTransactionType::Koreksi->value, 'required_if:reason,lainnya'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id', 'prohibited_unless:type,'.StockTransactionType::Pemakaian->value],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'quantity.not_in' => 'Jumlah tidak boleh 0.',
            'reason.required_if' => 'Pilih alasan penghapusan.',
            'reason.prohibited_unless' => 'Alasan hanya untuk Penghapusan.',
            'note.required_if' => 'Keterangan wajib diisi.',
            'room_id.prohibited_unless' => 'Ruangan hanya untuk Pemakaian.',
        ];
    }
}
