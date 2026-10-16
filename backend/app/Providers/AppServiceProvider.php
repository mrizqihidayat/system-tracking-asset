<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Halaman publik QR (tanpa login): batasi per IP.
        RateLimiter::for('public-assets', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Login: maks. 5 percobaan per menit per kombinasi username + IP (SPEC §15).
        RateLimiter::for('login', fn (Request $request) => Limit::perMinute(5)
            ->by(mb_strtolower(trim((string) $request->input('username'))).'|'.$request->ip())
            ->response(function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);

                return response()->json(['message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."], 429, $headers);
            }));
    }
}
