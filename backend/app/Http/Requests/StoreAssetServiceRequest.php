<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Http\Requests\Concerns\ValidatesStatusChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAssetServiceRequest extends FormRequest
{
    use ValidatesStatusChoice;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Catat servis: kondisi setelah servis (status aturan A6), servis berikutnya opsional.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'service_date' => ['required', 'date', 'before_or_equal:today'],
            'vendor' => ['required', 'string', 'max:150'],
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            ...$this->statusChoiceRules(),
            'next_service_date' => ['nullable', 'date', 'after:service_date'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            ...$this->statusChoiceMessages(),
            'service_date.before_or_equal' => 'Tanggal servis tidak boleh setelah hari ini.',
            'next_service_date.after' => 'Servis berikutnya harus setelah tanggal servis.',
        ];
    }
}
