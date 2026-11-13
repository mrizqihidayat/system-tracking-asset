<?php

namespace App\Services\EmployeeDirectory;

use RuntimeException;

/** API pegawai tidak bisa diakses (timeout, error server, file dummy tidak terbaca). */
class EmployeeDirectoryUnavailable extends RuntimeException {}
