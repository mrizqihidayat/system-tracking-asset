<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Asset>
 */
class AssetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('SHD-???-###'),
            'asset_type_id' => AssetType::factory(),
            'name' => fake()->words(3, true),
            'brand' => fake()->company(),
            'model' => fake()->bothify('??-####'),
            'serial_number' => fake()->bothify('SN-########'),
            'source_department_id' => Department::factory(),
            'room_id' => Room::factory(),
            'condition' => AssetCondition::Baik,
            'status' => AssetStatus::Tersedia,
            'created_by' => User::factory(),
        ];
    }
}
