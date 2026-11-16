<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Testing\TestResponse;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Login dan hapus aset dari test tidak boleh masuk ke log keamanan sungguhan (lihat SecurityLogTest).
        config(['logging.channels.security' => config('logging.channels.null')]);
    }

    /**
     * Terima kembali dengan foto kondisi (wajib bila kondisi Rusak Ringan/Rusak Berat, T36). Multipart, bukan JSON.
     *
     * @param  array<string, mixed>  $data
     */
    protected function returnLoanWithPhoto(int $loanId, array $data): TestResponse
    {
        return $this->patch("/api/loans/{$loanId}/return", $data + ['condition_photo' => UploadedFile::fake()->image('kondisi.jpg')], ['Accept' => 'application/json']);
    }
}
