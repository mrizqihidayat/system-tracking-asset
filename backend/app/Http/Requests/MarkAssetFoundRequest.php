<?php

namespace App\Http\Requests;

use App\Enums\AssetCondition;
use App\Http\Requests\Concerns\ValidatesStatusChoice;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkAssetFoundRequest extends FormRequest
{
    use ValidatesStatusChoice;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Tandai ditemukan: kondisi wajib, status aturan A6 (tidak pernah langsung Dipinjam).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'condition' => ['required', Rule::enum(AssetCondition::class)],
            ...$this->statusChoiceRules(),
            'note' => ['nullable', 'string', 'max:1000'],
            'party' => ['nullable', 'string', 'max:150'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return $this->statusChoiceMessages();
    }
}
