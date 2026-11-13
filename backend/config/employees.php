<?php

return [
    /*
     * Sumber data pegawai (T33): "dummy" = database/data/employees.json (data fiktif demo),
     * "http" = API pegawai INALUM (EMPLOYEE_API_URL + EMPLOYEE_API_TOKEN).
     */
    'driver' => env('EMPLOYEE_DIRECTORY_DRIVER', 'dummy'),

    'dummy_path' => database_path('data/employees.json'),

    'api_url' => env('EMPLOYEE_API_URL'),
    'api_token' => env('EMPLOYEE_API_TOKEN'),
    'api_timeout' => (int) env('EMPLOYEE_API_TIMEOUT', 5),

    // Hanya pegawai departemen ini yang bisa login dengan NIK.
    'shd_department' => env('EMPLOYEE_SHD_DEPARTMENT', 'SHD'),
];
