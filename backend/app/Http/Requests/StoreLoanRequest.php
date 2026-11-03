<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreLoanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kondisi keluar tidak diinput (otomatis dari kondisi aset). Tanggal pinjam boleh lampau
     * (pencatatan terlambat), tidak boleh di masa depan; batas "sebelum aset terdaftar" di LoanService.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_id' => ['required', 'integer', 'exists:assets,id'],
            'borrower_name' => ['required', 'string', 'max:150'],
            'borrower_unit' => ['required', 'string', 'max:100'],
            'purpose' => ['required', 'string', 'max:255'],
            'destination_room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'destination_note' => ['nullable', 'string', 'max:255'],
            'borrow_date' => ['nullable', 'date', 'before_or_equal:today'],
            'due_date' => ['required', 'date', 'after_or_equal:'.($this->input('borrow_date') ?: 'today')],
            'approval_number' => ['nullable', 'string', 'max:100'],
            'approval_date' => ['nullable', 'date'],
            'condition_out' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'borrow_date.before_or_equal' => 'Tanggal pinjam tidak boleh di masa depan.',
            'due_date.after_or_equal' => 'Rencana kembali tidak boleh sebelum tanggal pinjam.',
            'condition_out.prohibited' => 'Kondisi saat keluar diisi otomatis dari kondisi aset.',
        ];
    }
}
