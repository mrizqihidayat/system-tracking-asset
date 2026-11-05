<?php

namespace App\Http\Requests;

use App\Rules\UniqueCaseInsensitive;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

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
            'consumable_category_id' => ['nullable', 'integer', 'exists:consumable_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'quantity' => ['required', 'integer', 'min:0', 'max:'.DbInteger::MAX],
            'min_quantity' => ['required', 'integer', 'min:0', 'max:'.DbInteger::MAX],
            'provider_department_id' => ['required', 'integer', 'exists:departments,id'],
            'room_id' => ['nullable', 'integer', Rule::exists('rooms', 'id')->where('is_storage', true)],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['room_id.exists' => 'Lokasi simpan harus ruangan gudang.'];
    }

    protected function ignoreId(): ?int
    {
        return null;
    }
}
