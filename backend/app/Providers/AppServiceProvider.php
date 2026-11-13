<?php

namespace App\Providers;

use App\Observers\CacheInvalidationObserver;
use App\Services\EmployeeDirectory\DummyEmployeeDirectory;
use App\Services\EmployeeDirectory\EmployeeDirectory;
use App\Services\EmployeeDirectory\HttpEmployeeDirectory;
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
        // Direktori pegawai (T33): data fiktif saat demo, API INALUM saat deploy (EMPLOYEE_DIRECTORY_DRIVER).
        $this->app->singleton(EmployeeDirectory::class, fn () => config('employees.driver') === 'http'
            ? new HttpEmployeeDirectory((string) config('employees.api_url'), config('employees.api_token'), (int) config('employees.api_timeout'))
            : new DummyEmployeeDirectory((string) config('employees.dummy_path')));
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Cache dashboard & data master dikosongkan saat model terkait berubah.
        foreach (array_keys(CacheInvalidationObserver::GROUPS) as $model) {
            $model::observe(CacheInvalidationObserver::class);
        }

        // Halaman publik QR (tanpa login): batasi per IP.
        RateLimiter::for('public-assets', fn (Request $request) => Limit::perMinute(30)->by($request->ip()));

        // Login: maks. 5 percobaan per menit per username + IP, dan 20 per menit per IP (SPEC §15).
        // Username bukan teks tidak dipakai sebagai kunci; validasi yang menjawab 422.
        RateLimiter::for('login', function (Request $request) {
            $username = $request->input('username');
            $key = (is_string($username) ? mb_strtolower(trim($username)) : '').'|'.$request->ip();
            $tooMany = function (Request $request, array $headers) {
                $seconds = (int) ($headers['Retry-After'] ?? 60);

                return response()->json(['message' => "Terlalu banyak percobaan login. Coba lagi dalam {$seconds} detik."], 429, $headers);
            };

            return [
                Limit::perMinute(5)->by($key)->response($tooMany),
                Limit::perMinute(20)->by($request->ip())->response($tooMany),
            ];
        });

        // Ganti password sendiri: maks. 10 per menit per akun.
        RateLimiter::for('password', fn (Request $request) => Limit::perMinute(10)->by((string) $request->user()?->id));

        // Endpoint berat per pengguna (SPEC §14).
        foreach (['reports' => 10, 'labels' => 20, 'uploads' => 30] as $name => $perMinute) {
            RateLimiter::for($name, fn (Request $request) => Limit::perMinute($perMinute)
                ->by((string) ($request->user()?->id ?? $request->ip()))
                ->response(function (Request $request, array $headers) {
                    $seconds = (int) ($headers['Retry-After'] ?? 60);

                    return response()->json(['message' => "Terlalu banyak permintaan. Coba lagi dalam {$seconds} detik."], 429, $headers);
                }));
        }
    }
}
