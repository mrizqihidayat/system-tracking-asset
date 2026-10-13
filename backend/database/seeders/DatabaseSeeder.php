<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'staf1'],
            [
                'name' => 'Staf SHD',
                'password' => Hash::make('inalum2026'),
            ]
        );
    }
}