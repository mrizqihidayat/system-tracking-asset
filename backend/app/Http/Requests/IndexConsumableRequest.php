<?php

namespace App\Http\Requests;

use App\Enums\ConsumableStatus;
use App\Http\Requests\Concerns\ValidatesListQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexConsumableRequest extends FormRequest
{
    use ValidatesListQuery;

    public const DEFAULT_PER_PAGE = 15;

    /** Kunci sort => kolom (sort=kunci naik, sort=-kunci turun). */
    public const SORTABLE = ['name' => 'consumables.name', 'category' => 'category_name', 'quantity' => 'consumables.quantity'];

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
            ...$this->listRules(),
        ];
    }
}
