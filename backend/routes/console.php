<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Mode tarik (T34): salinan pegawai & departemen dari API pegawai, harian pukul 23.59 WIB, hanya bila EMPLOYEE_API_URL
// diisi (butuh cron `* * * * * php artisan schedule:run`). Dengan agen sinkron (T44) URL ini kosong dan jadwal tidak jalan,
// supaya tidak mencatat "gagal" tiap malam di atas hasil kiriman agen.
Schedule::command('employees:sync')
    ->dailyAt(config('employees.sync_time'))
    ->timezone(config('employees.sync_timezone'))
    ->when(fn (): bool => filled(config('employees.api_url')))
    ->withoutOverlapping();
