<?php

use App\Support\TrustedProxies;

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted Proxies
    |--------------------------------------------------------------------------
    |
    | Reverse proxy yang boleh menentukan IP pengunjung lewat X-Forwarded-For
    | (dibaca middleware TrustProxies bawaan Laravel). Kosong = tanpa proxy.
    |
    */

    'proxies' => TrustedProxies::parse(env('TRUSTED_PROXIES')),

];
