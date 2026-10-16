<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRequestCatalogRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * request_method teks bebas (A3).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'item_name' => ['required', 'string', 'max:150'],
            'provider_department_id' => ['required', 'integer', 'exists:departments,id'],
            'request_method' => ['required', 'string', 'max:50'],
            'must_return' => ['required', 'boolean'],
            'lead_time_days' => ['required', 'integer', 'min:0', 'max:365'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
