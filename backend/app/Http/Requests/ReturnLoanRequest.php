<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Loan;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReturnLoanRequest extends FormRequest
{
    public const RESULT_RETURNED = 'kembali';

    public const RESULT_LOST = 'hilang';

    public function authorize(): bool
    {
        return true;
    }

    /**
     * result=kembali: kondisi wajib, foto kondisi wajib jika tidak Baik, status A6, ruangan penyimpanan (default lokasi asal), catatan wajib jika tidak Baik.
     * result=hilang: hanya tanggal & catatan (wajib).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    protected function prepareForValidation(): void
    {
        StoreAssetFileRequest::rejectIfTooLarge($this->file('condition_photo'));
    }

    public function rules(): array
    {
        /** @var Loan $loan */
        $loan = $this->route('loan');
        $lost = $this->isLost();

        return [
            'result' => ['nullable', Rule::in([self::RESULT_RETURNED, self::RESULT_LOST])],
            'return_date' => ['nullable', 'date', 'after_or_equal:'.$loan->borrow_date->toDateString(), 'before_or_equal:now'],
            'return_condition' => $lost ? ['prohibited'] : ['required', Rule::enum(AssetCondition::class)],
            'status' => $lost ? ['prohibited'] : $this->statusRules(),
            'return_room_id' => $lost ? ['prohibited'] : ['nullable', 'integer', 'exists:rooms,id'],
            // Foto kondisi (T36): wajib jika kondisi tidak Baik, opsional jika Baik, tidak dipakai jika hilang.
            'condition_photo' => $lost
                ? ['prohibited']
                : [
                    Rule::requiredIf(fn () => in_array($this->input('return_condition'), [AssetCondition::RusakRingan->value, AssetCondition::RusakBerat->value], true)),
                    'nullable', 'file', 'mimes:jpg,jpeg,png,webp', 'mimetypes:image/jpeg,image/png,image/webp', StoreAssetFileRequest::validName(),
                ],
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
            'status.in' => $this->input('return_condition') === AssetCondition::RusakBerat->value
                ? 'Rusak Berat hanya bisa Perbaikan atau Tidak Layak Pakai.'
                : 'Rusak Ringan hanya bisa Tersedia atau Perbaikan.',
            'result.in' => 'Hasil harus kembali atau hilang.',
            'return_date.after_or_equal' => 'Tanggal kembali tidak boleh sebelum tanggal pinjam.',
            'return_date.before_or_equal' => 'Tanggal kembali tidak boleh di masa depan.',
            'note.required' => $this->isLost() ? 'Catatan kehilangan wajib diisi.' : 'Catatan wajib diisi jika kondisi tidak Baik.',
            'return_condition.prohibited' => 'Kondisi tidak diisi jika aset hilang.',
            'status.prohibited' => $this->isLost() ? 'Status tidak diisi jika aset hilang.' : 'Status tidak dipilih jika kondisi Baik.',
            'return_room_id.prohibited' => 'Ruangan tidak diisi jika aset hilang.',
            'condition_photo.required' => 'Foto kondisi wajib diunggah jika kondisi tidak Baik.',
            'condition_photo.mimes' => 'Foto harus berupa JPG, PNG, atau WebP.',
            'condition_photo.mimetypes' => 'Foto harus berupa JPG, PNG, atau WebP.',
            'condition_photo.prohibited' => 'Foto kondisi tidak diunggah jika aset hilang.',
        ];
    }

    /**
     * Pilihan status per kondisi: Rusak Ringan Tersedia/Perbaikan, Rusak Berat Perbaikan/Tidak Layak Pakai,
     * Baik otomatis Tersedia.
     *
     * @return list<mixed>
     */
    private function statusRules(): array
    {
        $allowed = match ($this->input('return_condition')) {
            AssetCondition::RusakRingan->value => [AssetStatus::Tersedia->value, AssetStatus::Perbaikan->value],
            AssetCondition::RusakBerat->value => [AssetStatus::Perbaikan->value, AssetStatus::TidakLayakPakai->value],
            default => [],
        };

        return [
            'nullable',
            Rule::in($allowed),
            Rule::prohibitedIf(fn () => $this->input('return_condition') === AssetCondition::Baik->value),
        ];
    }
}
