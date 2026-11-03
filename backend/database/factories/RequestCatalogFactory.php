<?php

namespace Database\Factories;

use App\Models\Department;
use App\Models\RequestCatalog;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RequestCatalog>
 */
class RequestCatalogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_name' => fake()->words(2, true),
            'provider_department_id' => Department::factory(),
            'must_return' => false,
            'lead_time_days' => fake()->numberBetween(1, 7),
        ];
    }
}
