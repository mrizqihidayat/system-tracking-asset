<?php

namespace App\Http\Requests;

use App\Models\Room;
use App\Rules\UniqueCaseInsensitive;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdateRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nama ruangan unik per gedung (gedung tidak bisa diganti).
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Room $room */
        $room = $this->route('room');

        return [
            'name' => ['required', 'string', 'max:100', new UniqueCaseInsensitive('rooms', ignoreId: $room->id, where: ['building_id' => $room->building_id])],
        ];
    }
}
