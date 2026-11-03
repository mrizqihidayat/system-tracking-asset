<?php

namespace App\Http\Requests;

use App\Models\Consumable;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateConsumableRequest extends StoreConsumableRequest
{
    /**
     * Kirim field yang berubah saja. Jumlah tidak diubah di sini (PATCH /consumables/{id}/quantity).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['quantity']);

        foreach ($rules as $field => $fieldRules) {
            $rules[$field] = ['sometimes', ...$fieldRules];
        }

        return [...$rules, 'quantity' => ['prohibited']];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['quantity.prohibited' => 'Jumlah diubah lewat ubah jumlah.'];
    }

    protected function ignoreId(): ?int
    {
        return $this->consumable()->id;
    }

    protected function currentIsStored(): bool
    {
        return $this->consumable()->is_stored;
    }

    protected function currentQuantity(): int
    {
        return $this->consumable()->quantity;
    }

    protected function currentProviderId(): ?int
    {
        return $this->consumable()->provider_department_id;
    }

    private function consumable(): Consumable
    {
        /** @var Consumable */
        return $this->route('consumable');
    }
}
