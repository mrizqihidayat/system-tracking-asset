<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Login dan hapus aset dari test tidak boleh masuk ke log keamanan sungguhan (lihat SecurityLogTest).
        config(['logging.channels.security' => config('logging.channels.null')]);
    }
}
