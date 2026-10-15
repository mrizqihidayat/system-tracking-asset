<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmDamageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Catatan konfirmasi wajib; dokumen (no. berita acara/memo) opsional. Waktu & staf otomatis.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'damage_confirmation_note' => ['required', 'string', 'max:1000'],
            'damage_confirmation_document' => ['nullable', 'string', 'max:100'],
        ];
    }
}
