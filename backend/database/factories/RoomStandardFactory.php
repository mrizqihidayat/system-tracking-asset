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
        ];
    }
}
