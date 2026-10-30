<?php

namespace App\Http\Requests;

use App\Enums\RequestStatus;
use App\Http\Requests\Concerns\ValidatesListQuery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexSupplyRequestRequest extends FormRequest
{
    use ValidatesListQuery;

    public const DEFAULT_PER_PAGE = 15;

    /** Kunci sort => kolom (sort=kunci naik, sort=-kunci turun). */
    public const SORTABLE = ['request_date' => 'supply_requests.request_date', 'item_name' => 'supply_requests.item_name', 'quantity' => 'supply_requests.quantity', 'status' => 'supply_requests.status'];

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
            'status' => ['nullable', Rule::enum(RequestStatus::class)],
            'provider_department_id' => ['nullable', 'integer'],
            'room_id' => ['nullable', 'integer'],
            'unresponded' => ['nullable', 'boolean'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            ...$this->listRules(),
        ];
    }
}
