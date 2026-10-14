<?php

namespace Database\Factories;

use App\Models\Consumable;
use App\Models\Department;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Consumable>
 */
class ConsumableFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'category' => 'ATK',
            'unit' => 'pcs',
            'quantity' => fake()->numberBetween(0, 100),
            'min_quantity' => 10,
            'provider_department_id' => Department::factory(),
        ];
    }
}
