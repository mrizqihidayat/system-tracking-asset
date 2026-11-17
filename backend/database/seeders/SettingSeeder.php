<?php

namespace Database\Seeders;

use App\Models\Setting;
use App\Services\SettingService;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    /** Nilai bawaan pengaturan (T40); baris yang sudah ada tidak ditimpa. */
    public function run(): void
    {
        foreach (SettingService::DEFAULTS as $key => $value) {
            Setting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
