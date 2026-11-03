<?php

namespace App\Http\Requests;

use App\Models\RequestCatalog;
use App\Rules\UniqueCaseInsensitive;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

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
            'is_stored' => ['sometimes', 'boolean'],
            'provider_department_id' => ['required', 'integer', 'exists:departments,id'],
            'catalog_id' => ['nullable', 'integer', 'exists:request_catalog,id', $this->catalogMatchesProvider()],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Barang yang tidak disimpan SHD tidak punya batas minimum.
     */
    protected function prepareForValidation(): void
    {
        if (! $this->isStored()) {
            $this->merge(['min_quantity' => 0]);
        }
    }

    /**
     * Barang yang masih punya jumlah tidak bisa ditandai tidak disimpan (stok tidak hilang diam-diam).
     *
     * @return list<callable>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty() || $this->isStored()) {
                return;
            }

            if ((int) ($this->input('quantity') ?? $this->currentQuantity()) > 0) {
                $validator->errors()->add('is_stored', 'Barang yang masih ada jumlahnya tidak bisa ditandai tidak disimpan.');
            }
        }];
    }

    private function isStored(): bool
    {
        return $this->boolean('is_stored', $this->currentIsStored());
    }

    protected function currentIsStored(): bool
    {
        return true;
    }

    protected function currentQuantity(): int
    {
        return 0;
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
