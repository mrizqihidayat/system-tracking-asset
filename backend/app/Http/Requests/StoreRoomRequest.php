<?php

namespace App\Http\Requests;

use App\Models\Building;
use App\Rules\UniqueCaseInsensitive;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreRoomRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Nama ruangan unik per gedung.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Building $building */
        $building = $this->route('building');

        return [
            'name' => ['required', 'string', 'max:100', new UniqueCaseInsensitive('rooms', where: ['building_id' => $building->id])],
        ];
    }
}
