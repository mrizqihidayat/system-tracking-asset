<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sanctum:prune-expired --hours=24')->daily();

// Salinan pegawai & departemen dari API pegawai (T34): harian pukul 02.00 WIB. Produksi butuh cron `* * * * * php artisan schedule:run`.
Schedule::command('employees:sync')
    ->dailyAt(config('employees.sync_time'))
    ->timezone(config('employees.sync_timezone'))
    ->withoutOverlapping();
