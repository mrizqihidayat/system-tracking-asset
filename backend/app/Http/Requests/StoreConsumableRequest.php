<?php

namespace App\Http\Requests;

use App\Rules\UniqueCaseInsensitive;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreConsumableRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150', new UniqueCaseInsensitive('consumables', ignoreId: $this->ignoreId())],
            'category' => ['nullable', 'string', 'max:100'],
            'unit' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.DbInteger::MAX],
            'min_quantity' => ['required', 'integer', 'min:0', 'max:'.DbInteger::MAX],
            'provider_department_id' => ['required', 'integer', 'exists:departments,id'],
            'storage_location' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    protected function ignoreId(): ?int
    {
        return null;
    }
}
