<?php

namespace App\Console\Commands;

use App\Models\EmployeeSyncRun;
use App\Services\EmployeeSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('employees:sync')]
#[Description('Sinkronkan salinan lokal pegawai dan departemen dari API pegawai (EMPLOYEE_API_URL)')]
class SyncEmployees extends Command
{
    /**
     * Terjadwal harian pukul 02.00 WIB; juga dijalankan manual untuk setup awal dan keadaan darurat lewat server.
     */
    public function handle(EmployeeSyncService $sync): int
    {
        try {
            $run = $sync->run();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $summary = "{$run->total} pegawai: {$run->created} baru, {$run->updated} diperbarui, {$run->deactivated} dinonaktifkan, {$run->skipped} dilewati.";

        if ($run->status === EmployeeSyncRun::SUCCESS) {
            $this->info("Sinkronisasi berhasil. {$summary}");

            return self::SUCCESS;
        }

        $this->error("Sinkronisasi {$run->status}: {$run->message}");

        return self::FAILURE;
    }
}
