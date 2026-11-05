<?php

namespace App\Http\Requests;

use App\Models\Consumable;
use Illuminate\Contracts\Validation\ValidationRule;

class UpdateConsumableRequest extends StoreConsumableRequest
{
    /**
     * Kirim field yang berubah saja. Jumlah tidak diubah di sini (POST /consumables/{id}/transactions).
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
        return ['quantity.prohibited' => 'Jumlah diubah lewat transaksi stok.'];
    }

    protected function ignoreId(): ?int
    {
        return $this->consumable()->id;
    }

    private function consumable(): Consumable
    {
        /** @var Consumable */
        return $this->route('consumable');
    }
}
