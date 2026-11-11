<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class HandOverLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tanggal penyerahan sebenarnya (default hari ini): boleh sebelum/pada/setelah tanggal rencana,
     * tidak di masa depan; batas terhadap aset & rencana kembali di LoanService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'handover_date' => ['nullable', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'handover_date.before_or_equal' => 'Tanggal penyerahan tidak boleh di masa depan.',
        ];
    }
}
