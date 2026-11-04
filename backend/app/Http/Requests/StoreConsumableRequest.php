<?php

namespace App\Http\Requests;

use App\Models\RequestCatalog;
use App\Rules\UniqueCaseInsensitive;
use Closure;
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
            'quantity' => ['required', 'integer', 'min:0'],
            'min_quantity' => ['required', 'integer', 'min:0'],
            'provider_department_id' => ['required', 'integer', 'exists:departments,id'],
            'catalog_id' => ['nullable', 'integer', 'exists:request_catalog,id', $this->catalogMatchesProvider()],
            'storage_location' => ['nullable', 'string', 'max:150'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Katalog harus dari unit yang sama dengan "minta ke".
     */
    protected function catalogMatchesProvider(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $providerId = $this->input('provider_department_id') ?? $this->currentProviderId();
            $catalogProvider = RequestCatalog::query()->whereKey($value)->value('provider_department_id');

            if ($catalogProvider !== null && (int) $catalogProvider !== (int) $providerId) {
                $fail("Katalog harus dari unit yang sama dengan 'minta ke'.");
            }
        };
    }

    protected function ignoreId(): ?int
    {
        return null;
    }

    protected function currentProviderId(): ?int
    {
        return null;
    }
}
