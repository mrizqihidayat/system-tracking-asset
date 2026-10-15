<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Http\Requests\Concerns\ValidatesStatusChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetRequest extends FormRequest
{
    use ValidatesStatusChoice;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Kondisi default Baik. Status hanya boleh dipilih jika Rusak Ringan (Tersedia/Perbaikan, SPEC §5).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'asset_type_id' => ['required', 'integer', 'exists:asset_types,id'],
            ...$this->dataRules(),
            'condition' => ['nullable', Rule::enum(AssetCondition::class)],
            ...$this->statusChoiceRules(),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->statusChoiceMessages();
    }

    /**
     * Field data aset yang sama untuk tambah & ubah.
     *
     * @return array<string, array<mixed>>
     */
    protected function dataRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'brand' => ['nullable', 'string', 'max:100'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'specification' => ['nullable', 'string', 'max:5000'],
            'source_department_id' => ['required', 'integer', 'exists:departments,id'],
            'room_id' => ['required', 'integer', 'exists:rooms,id'],
            'pic_name' => ['nullable', 'string', 'max:150'],
            'finance_code' => ['nullable', 'string', 'max:100'],
            'acquisition_date' => ['nullable', 'date'],
            'next_service_date' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
