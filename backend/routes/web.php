<?php

use Illuminate\Support\Facades\Route;

// Proyek API-only: halaman utama hanya penanda hidup (cek deploy/health check).
Route::get('/', fn () => response()->json(['app' => config('app.name'), 'status' => 'ok']));
