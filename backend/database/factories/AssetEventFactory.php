<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Asset;
use App\Models\AssetEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AssetEvent>
 */
class AssetEventFactory extends Factory
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
            'event_type' => EventType::Registrasi,
            'event_date' => now(),
            'party' => 'SHD',
            'recorded_by' => User::factory(),
        ];
    }
}
