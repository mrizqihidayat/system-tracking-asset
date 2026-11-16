<?php

return [
    /*
     * API pegawai INALUM (T34). Hanya satu URL: GET {api_url} langsung mengembalikan SEMUA pegawai
     * (tanpa path tambahan). Dipanggil oleh `php artisan employees:sync` (terjadwal harian), bukan per permintaan.
     */
    'api_url' => env('EMPLOYEE_API_URL'),

    // API key dikirim di header `api_key_header`. Jangan dicatat di log.
    'api_key' => env('EMPLOYEE_API_KEY'),
    'api_key_header' => env('EMPLOYEE_API_KEY_HEADER', 'X-API-Key'),

    // Kunci array pegawai bila respons berupa objek pembungkus (mis. "data"); kosong = respons berupa array langsung.
    'data_key' => env('EMPLOYEE_API_DATA_KEY'),

    'api_timeout' => (int) env('EMPLOYEE_API_TIMEOUT', 30),

    // Hanya pegawai departemen ini (kode, kolom departemen di API) yang bisa login dengan NIK.
    'shd_department' => env('EMPLOYEE_SHD_DEPARTMENT', 'SHD'),

    // Vercel Cron (T38): GET /api/internal/cron/employees-sync dengan `Authorization: Bearer {CRON_SECRET}`. Kosong = endpoint mati.
    'cron_secret' => env('CRON_SECRET'),

    // API pegawai tiruan di aplikasi ini (GET /api/mock/employees), hanya untuk uji coba. Hapus saat produksi.
    'mock_enabled' => (bool) env('EMPLOYEE_MOCK_ENABLED', false),

    // Jadwal sinkronisasi harian (zona waktu WIB).
    'sync_time' => env('EMPLOYEE_SYNC_TIME', '02:00'),
    'sync_timezone' => 'Asia/Jakarta',
];
