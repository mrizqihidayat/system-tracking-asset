<?php

namespace App\Http\Requests;

use App\Enums\RequestStatus;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupplyRequestStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Status tujuan saja; Diajukan (status awal) tidak bisa dipilih kembali dan Diterima sebagian ditentukan dari jumlah.
     * received_quantity: jumlah yang benar-benar datang (barang stok); kosong = semua sisa.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in([
                RequestStatus::Diterima->value,
                RequestStatus::Dikembalikan->value,
                RequestStatus::Dibatalkan->value,
            ])],
            'received_quantity' => ['nullable', 'integer', 'min:1', 'max:'.DbInteger::MAX, 'prohibited_unless:status,'.RequestStatus::Diterima->value],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
