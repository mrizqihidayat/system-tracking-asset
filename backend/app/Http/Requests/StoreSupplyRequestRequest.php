<?php

namespace App\Http\Requests;

use App\Models\RequestCatalog;
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
     * Dengan katalog: nama item & unit penyedia boleh dikosongkan (diisi dari katalog).
     * Cara permintaan wajib per permintaan (teks bebas dengan saran).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'request_date' => ['nullable', 'date'],
            'catalog_id' => ['nullable', 'integer', 'exists:request_catalog,id', $this->catalogMatchesProvider()],
            'item_name' => ['required_without:catalog_id', 'nullable', 'string', 'max:150'],
            'quantity' => ['required', 'integer', 'min:1'],
            'purpose' => ['nullable', 'string', 'max:255'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'provider_department_id' => ['required_without:catalog_id', 'nullable', 'integer', 'exists:departments,id'],
            'request_method' => ['required', 'string', 'max:50'],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
    }

    /**
     * Unit penyedia (bila dikirim) harus sama dengan unit katalog.
     */
    protected function catalogMatchesProvider(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) {
            $providerId = $this->input('provider_department_id');
            $catalogProvider = RequestCatalog::query()->whereKey($value)->value('provider_department_id');

            if ($providerId !== null && $catalogProvider !== null && (int) $catalogProvider !== (int) $providerId) {
                $fail('Katalog harus dari unit penyedia yang sama.');
            }
        };
    }
}
