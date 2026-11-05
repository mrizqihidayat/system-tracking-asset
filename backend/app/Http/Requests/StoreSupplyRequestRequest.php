<?php

namespace App\Http\Requests;

use App\Models\RequestCatalog;
use App\Support\DbInteger;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplyRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Dengan katalog: nama item & departemen penyedia boleh dikosongkan (diisi dari katalog).
     * Barang stok tidak memakai katalog: nama item & departemen penyedia dikirim langsung, tanpa lead time.
     * Cara permintaan wajib per permintaan (teks bebas dengan saran).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_date' => ['nullable', 'date'],
            'catalog_id' => ['nullable', 'integer', 'exists:request_catalog,id', $this->catalogMatchesProvider()],
            'consumable_id' => ['nullable', 'integer', 'exists:consumables,id', 'prohibits:catalog_id'],
            'item_name' => ['required_without:catalog_id', 'nullable', 'string', 'max:150'],
            'must_return' => ['nullable', 'boolean'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.DbInteger::MAX],
            'purpose' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'provider_department_id' => ['required_without:catalog_id', 'nullable', 'integer', 'exists:departments,id'],
            'request_method' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['consumable_id.prohibits' => 'Barang stok tidak memakai katalog.'];
    }

    /**
     * Departemen penyedia (bila dikirim) harus sama dengan departemen katalog.
     */
    protected function catalogMatchesProvider(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $providerId = $this->input('provider_department_id');
            $catalogProvider = RequestCatalog::query()->whereKey($value)->value('provider_department_id');

            if ($providerId !== null && $catalogProvider !== null && (int) $catalogProvider !== (int) $providerId) {
                $fail('Katalog harus dari departemen penyedia yang sama.');
            }
        };
    }
}
