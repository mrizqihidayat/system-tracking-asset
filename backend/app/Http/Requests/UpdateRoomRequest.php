<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Rules\UniqueCaseInsensitive;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nama ruangan unik per gedung (gedung tidak bisa diganti). Tanda gudang tidak bisa dilepas selama masih menjadi lokasi simpan stok.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Room $room */
        $room = $this->route('room');

        return [
            'name' => ['required', 'string', 'max:100', new UniqueCaseInsensitive('rooms', ignoreId: $room->id, where: ['building_id' => $room->building_id])],
            'is_storage' => ['sometimes', 'boolean', function (string $attribute, mixed $value, Closure $fail) use ($room) {
                $count = filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 0 : $room->storedConsumables()->count();

                if ($count > 0) {
                    $fail("Ruangan masih menjadi lokasi simpan {$count} barang stok.");
                }
            }],
        ];
    }
}
