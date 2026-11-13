<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'department' => 'SHD',
            'source' => User::SOURCE_MANUAL,
            'password' => static::$password ??= Hash::make('password'),
            'must_change_password' => false,
        ];
    }

    /** Akun pegawai SHD (login NIK). */
    public function employee(string $nik): static
    {
        return $this->state(['nik' => $nik, 'username' => null, 'source' => User::SOURCE_API]);
    }
}
