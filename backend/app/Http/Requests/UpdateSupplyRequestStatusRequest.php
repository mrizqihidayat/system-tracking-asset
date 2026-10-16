<?php

namespace App\Http\Requests;

use App\Enums\RequestStatus;
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
     * Status tujuan saja; Diajukan (status awal) tidak bisa dipilih kembali.
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
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
