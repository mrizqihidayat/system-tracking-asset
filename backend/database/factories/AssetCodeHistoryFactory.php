<?php

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCodeHistory;
use App\Models\AssetType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetCodeHistory>
 */
class AssetCodeHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'asset_id' => Asset::factory(),
            'asset_type_id' => AssetType::factory(),
            'code' => fake()->unique()->bothify('SHD-???-###'),
            'replaced_at' => now(),
            'replaced_by' => User::factory(),
        ];
    }
}
