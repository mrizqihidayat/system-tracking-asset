<?php

namespace Database\Factories;

use App\Models\Employee;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'nik' => (string) fake()->unique()->numerify('9########'),
            'name' => strtoupper(fake()->name()),
            'group_name' => 'GRP',
            'division' => 'DIV',
            'department_code' => 'DEP',
            'section' => null,
            'position' => 'Staf',
            'email' => fake()->unique()->safeEmail(),
            'is_active' => true,
            'synced_at' => now(),
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    public function inDepartment(?string $code): static
    {
        return $this->state(fn (array $attributes) => ['department_code' => $code]);
    }
}
