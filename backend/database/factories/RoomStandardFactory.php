<?php

namespace Database\Factories;

use App\Enums\CountMethod;
use App\Models\Consumable;
use App\Models\Room;
use App\Models\RoomStandard;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RoomStandard>
 */
class RoomStandardFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'room_id' => Room::factory(),
            'standard_quantity' => fake()->numberBetween(1, 30),
            'count_method' => CountMethod::Manual,
            'consumable_id' => fn (array $attributes) => $attributes['count_method'] === CountMethod::Qr ? null : Consumable::factory(),
            'actual_quantity' => 0,
            // Baris Manual dengan Ada terisi dianggap sudah dicek; actual_quantity null = Belum dicek (gaya lama test).
            'checked_at' => fn (array $attributes) => CountMethod::from($attributes['count_method'] instanceof CountMethod ? $attributes['count_method']->value : $attributes['count_method']) !== CountMethod::Qr
                && $attributes['actual_quantity'] !== null ? now() : null,
        ];
    }

    /**
     * Barang yang baru ditambahkan ke ruangan: Ada = saldo, belum dicek.
     */
    public function unchecked(): static
    {
        return $this->state(['checked_at' => null]);
    }
}
