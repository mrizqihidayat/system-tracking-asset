<?php

namespace App\Http\Requests;

use App\Enums\ConsumableStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConsumableRequest extends FormRequest
{
    public const DEFAULT_PER_PAGE = 15;

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
            'status' => ['nullable', Rule::enum(ConsumableStatus::class)],
            'category' => ['nullable', 'string', 'max:100'],
            'provider_department_id' => ['nullable', 'integer'],
            'building_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }
}
