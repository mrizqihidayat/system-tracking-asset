<?php

namespace Database\Factories;

use App\Enums\RequestStatus;
use App\Models\Department;
use App\Models\RequestCatalog;
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
            'request_method' => 'Memo',
            // Snapshot dari katalog seperti SupplyRequestService::resolve().
            'lead_time_days' => fn (array $attributes) => self::catalog($attributes)?->lead_time_days,
            'must_return' => fn (array $attributes) => (bool) self::catalog($attributes)?->must_return,
            'status' => RequestStatus::Diajukan,
            'created_by' => User::factory(),
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private static function catalog(array $attributes): ?RequestCatalog
    {
        $id = $attributes['catalog_id'] ?? null;

        return $id ? RequestCatalog::query()->find($id instanceof RequestCatalog ? $id->getKey() : $id) : null;
    }
}
