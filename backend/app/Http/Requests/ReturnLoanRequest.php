<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Http\Requests\Concerns\ValidatesStatusChoice;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnLoanRequest extends FormRequest
{
    use ValidatesStatusChoice;

    public const RESULT_RETURNED = 'kembali';

    public const RESULT_LOST = 'hilang';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * result=kembali: kondisi wajib, status A6, ruangan penyimpanan (default lokasi asal), catatan wajib jika tidak Baik.
     * result=hilang: hanya tanggal & catatan (wajib).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');
        $lost = $this->isLost();

        return [
            'result' => ['nullable', Rule::in([self::RESULT_RETURNED, self::RESULT_LOST])],
            'return_date' => ['nullable', 'date', 'after_or_equal:'.$loan->borrow_date->toDateString(), 'before_or_equal:now'],
            'return_condition' => $lost ? ['prohibited'] : ['required', Rule::enum(AssetCondition::class)],
            ...($lost ? ['status' => ['prohibited']] : $this->statusChoiceRules()),
            'return_room_id' => $lost ? ['prohibited'] : ['nullable', 'integer', 'exists:rooms,id'],
            'note' => [
                'nullable',
                'string',
                'max:1000',
                Rule::requiredIf(fn () => $lost || ($this->filled('return_condition') && $this->input('return_condition') !== AssetCondition::Baik->value)),
            ],
        ];
    }

    public function isLost(): bool
    {
        return $this->input('result') === self::RESULT_LOST;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->statusChoiceMessages(),
            'result.in' => 'Hasil harus kembali atau hilang.',
            'return_date.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'return_date.before_or_equal' => 'Tanggal kembali tidak boleh di masa depan.',
            'note.required' => $this->isLost() ? 'Catatan kehilangan wajib diisi.' : 'Catatan wajib diisi jika kondisi tidak Baik.',
            'return_condition.prohibited' => 'Kondisi tidak diisi jika aset hilang.',
            'status.prohibited' => $this->isLost() ? 'Status tidak diisi jika aset hilang.' : 'Status hanya bisa dipilih jika kondisi Rusak Ringan.',
            'return_room_id.prohibited' => 'Ruangan tidak diisi jika aset hilang.',
        ];
    }

    /**
     * statusChoiceRules() memakai field "condition"; di sini kondisinya "return_condition".
     */
    protected function prepareForValidation(): void
    {
        $this->merge(['condition' => $this->input('return_condition')]);
    }
}
