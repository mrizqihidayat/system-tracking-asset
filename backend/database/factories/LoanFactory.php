<?php

namespace Database\Factories;

use App\Enums\AssetCondition;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Loan>
 */
class LoanFactory extends Factory
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
            'borrower_name' => fake()->name(),
            'borrower_unit' => fake()->regexify('[A-Z]{3}'),
            'purpose' => fake()->sentence(3),
            'borrow_date' => today(),
            'due_date' => today()->addDays(3),
            'condition_out' => AssetCondition::Baik,
            'borrowed_by' => User::factory(),
            'status' => LoanStatus::Dipinjam,
        ];
    }
}
