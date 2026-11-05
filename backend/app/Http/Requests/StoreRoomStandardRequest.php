<?php

namespace App\Http\Requests;

use App\Enums\CountMethod;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Support\DbInteger;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Baris standar ruangan (SPEC §8); dipakai juga untuk PUT (definisi lengkap).
 * Pilih tepat satu: jenis aset atau barang stok, masing-masing sekali per ruangan.
 * Jumlah Ada barang stok hanya lewat POST /rooms/{id}/check.
 */
class StoreRoomStandardRequest extends FormRequest
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
            'standard_quantity' => ['required', 'integer', 'min:1', 'max:'.DbInteger::MAX],
            'asset_type_id' => ['nullable', 'integer', 'exists:asset_types,id', 'required_without:consumable_id', 'prohibits:consumable_id', $this->uniqueInRoom('asset_type_id')],
            'consumable_id' => ['nullable', 'integer', 'exists:consumables,id', $this->uniqueInRoom('consumable_id')],
            'actual_quantity' => ['prohibited'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'asset_type_id.required_without' => 'Pilih jenis aset atau barang stok.',
            'asset_type_id.prohibits' => 'Pilih salah satu: jenis aset atau barang stok.',
            'asset_type_id.unique' => 'Jenis aset ini sudah punya standar di ruangan ini.',
            'consumable_id.unique' => 'Barang ini sudah ada di ruangan ini.',
            'actual_quantity.prohibited' => 'Jumlah Ada diisi lewat simpan hasil cek.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function standardData(): array
    {
        $data = $this->validated();

        return [
            'standard_quantity' => $data['standard_quantity'],
            'asset_type_id' => $data['asset_type_id'] ?? null,
            'consumable_id' => $data['consumable_id'] ?? null,
            'count_method' => empty($data['asset_type_id']) ? CountMethod::Manual->value : CountMethod::Qr->value,
        ];
    }

    private function uniqueInRoom(string $column): object
    {
        $standard = $this->route('roomStandard');
        $room = $this->route('room');
        $roomId = $standard instanceof RoomStandard ? $standard->room_id : ($room instanceof Room ? $room->id : null);

        return Rule::unique('room_standards', $column)
            ->where('room_id', $roomId)
            ->ignore($standard instanceof RoomStandard ? $standard->id : null);
    }
}
