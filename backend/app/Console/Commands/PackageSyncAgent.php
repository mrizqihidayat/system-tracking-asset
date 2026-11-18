<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use ZipArchive;

#[Signature('agent:package {--output= : Folder tujuan file zip (bawaan: root repo)}')]
#[Description('Buat employee-sync-agent-vX.Y.zip berisi agen sinkron pegawai (T44), siap dikirim ke tim IT perusahaan')]
class PackageSyncAgent extends Command
{
    /** Hanya file ini yang masuk paket; .env, log, dan file lain di folder agen tidak pernah ikut. */
    public const FILES = ['sync.php', '.env.example', 'README.md', 'CHECKLIST.md'];

    /**
     * Versi diambil dari AGENT_VERSION di sync.php. Isi zip: satu folder `employee-sync-agent/` dengan FILES.
     */
    public function handle(): int
    {
        $source = dirname(base_path()).DIRECTORY_SEPARATOR.'employee-sync-agent';

        foreach (self::FILES as $file) {
            if (! is_file($source.DIRECTORY_SEPARATOR.$file)) {
                $this->error("File agen tidak ditemukan: employee-sync-agent/{$file}");

                return self::FAILURE;
            }
        }

        if (! preg_match("/^const AGENT_VERSION = '(\d+\.\d+)';/m", (string) file_get_contents($source.DIRECTORY_SEPARATOR.'sync.php'), $match)) {
            $this->error('AGENT_VERSION (format X.Y) tidak ditemukan di employee-sync-agent/sync.php.');

            return self::FAILURE;
        }

        $output = rtrim((string) ($this->option('output') ?: dirname(base_path())), '/\\');
        $zipPath = $output.DIRECTORY_SEPARATOR."employee-sync-agent-v{$match[1]}.zip";
        $zip = new ZipArchive;

        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->error("Tidak bisa membuat {$zipPath}.");

            return self::FAILURE;
        }

        foreach (self::FILES as $file) {
            $zip->addFile($source.DIRECTORY_SEPARATOR.$file, "employee-sync-agent/{$file}");
        }

        $zip->close();

        $this->info("Paket agen v{$match[1]} dibuat: {$zipPath}");

        return self::SUCCESS;
    }
}
