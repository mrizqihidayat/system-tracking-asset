<?php

namespace App\Support;

/**
 * Batas kolom `integer` PostgreSQL. Nilai di atasnya harus ditolak validasi agar tidak menjadi error 500.
 */
class DbInteger
{
    public const MAX = 2147483647;
}
