<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Akun bersama contoh (password hanya untuk demo). Akun pegawai SHD tidak di-seed: dibuat otomatis saat
        // login pertama dengan NIK dari salinan pegawai (isi dengan `php artisan employees:sync`; seeder tidak memanggil API, T34).
        User::updateOrCreate(
            ['username' => 'staf1'],
            [
                'name' => 'Staf SHD',
                'department' => 'SHD',
                'source' => User::SOURCE_MANUAL,
                'password' => Hash::make('inalum2026'),
            ]
        );

        $this->call([
            MasterDataSeeder::class,
            AssetSeeder::class,
            RequestSeeder::class,
            ConsumableSeeder::class,
            RoomStandardSeeder::class,
        ]);
    }
}
