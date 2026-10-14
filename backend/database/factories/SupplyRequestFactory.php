<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\SupplyRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplyRequest>
 */
class SupplyRequestFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'request_date' => today(),
            'item_name' => fake()->words(2, true),
            'quantity' => fake()->numberBetween(1, 20),
            'provider_department_id' => Department::factory(),
            'status' => RequestStatus::Diajukan,
            'created_by' => User::factory(),
        ];
    }
}
