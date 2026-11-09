<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Menjaga aturan CLAUDE.md lewat pemindaian kode `app/` (tanpa database).
 */
class ArchitectureRulesTest extends TestCase
{
    /**
     * @return array<string, string> path relatif dari app/ => isi file
     */
    private function appFiles(): array
    {
        $root = dirname(__DIR__, 2).DIRECTORY_SEPARATOR.'app';
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() === 'php') {
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $files[$relative] = file_get_contents($file->getPathname());
            }
        }

        $this->assertNotEmpty($files);

        return $files;
    }

    /**
     * @param  list<string>  $patterns
     * @param  list<string>  $allowed  path relatif yang boleh
     * @return list<string> file pelanggar
     */
    private function filesMatching(array $patterns, array $allowed): array
    {
        $violations = [];

        foreach ($this->appFiles() as $path => $code) {
            if (in_array($path, $allowed, true)) {
                continue;
            }

            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $code) === 1) {
                    $violations[] = $path;
                    break;
                }
            }
        }

        return $violations;
    }

    public function test_asset_events_are_written_only_by_asset_event_service(): void
    {
        $violations = $this->filesMatching([
            '/AssetEvent::(create|insert|forceCreate|firstOrCreate|updateOrCreate)\b/',
            '/new\s+AssetEvent\b/',
            '/->events\(\)\s*->\s*(create|insert|forceCreate|createMany|save|saveMany)\b/',
            '/->events\s*->\s*(create|save)\b/',
            '/table\(\s*[\'"]asset_events[\'"]\s*\)\s*->\s*insert/',
        ], ['Services/AssetEventService.php']);

        $this->assertSame([], $violations, 'Event aset hanya boleh ditulis lewat AssetEventService::record().');
    }

    public function test_asset_events_are_deleted_only_by_asset_service(): void
    {
        $violations = $this->filesMatching([
            '/AssetEvent::query\(\)[^;]*->(delete|forceDelete|truncate)\b/s',
            '/AssetEvent::(truncate|destroy)\b/',
        ], ['Services/AssetService.php']);

        $this->assertSame([], $violations, 'Riwayat aset hanya boleh dihapus bersama aset oleh AssetService::delete().');
    }

    public function test_assets_are_hard_deleted_only_by_asset_service(): void
    {
        $violations = $this->filesMatching([
            '/Asset::(destroy|truncate)\b/',
            '/Asset::query\(\)[^;]*->(delete|forceDelete)\b/s',
            '/\$asset->(delete|forceDelete)\(\)/',
        ], ['Services/AssetService.php']);

        $this->assertSame([], $violations, 'Hapus aset hanya lewat AssetService::delete() (delete-impact + confirm_code).');
    }

    public function test_stock_quantity_changes_only_through_consumable_service(): void
    {
        $violations = $this->filesMatching([
            '/\bStockTransaction::(create|insert|forceCreate|firstOrCreate|updateOrCreate)\b/',
            '/new\s+StockTransaction\b/',
            '/->transactions\(\)\s*->\s*(create|insert|forceCreate|createMany|save|saveMany)\b/',
            '/->(increment|decrement)\(\s*[\'"]quantity[\'"]/',
            '/Consumable::create\(/',
            '/->update\(\[[^\]]*[\'"]quantity[\'"]\s*=>/',
        ], ['Services/ConsumableService.php']);

        $this->assertSame([], $violations, 'Jumlah stok hanya boleh berubah lewat ConsumableService::record().');
    }

    public function test_room_stock_changes_only_through_room_stock_service(): void
    {
        $violations = $this->filesMatching([
            '/RoomStockTransaction::(create|insert|forceCreate|firstOrCreate|updateOrCreate)\b/',
            '/new\s+RoomStockTransaction\b/',
            '/->(increment|decrement)\(\s*[\'"]actual_quantity[\'"]/',
            '/->update\(\[[^\]]*[\'"]actual_quantity[\'"]\s*=>/',
        ], ['Services/RoomStockService.php']);

        $this->assertSame([], $violations, 'Jumlah Ada barang habis pakai ruangan hanya boleh berubah lewat RoomStockService::record().');
    }

    public function test_dangerous_database_commands_are_not_used_in_application_code(): void
    {
        $violations = $this->filesMatching([
            '/migrate:(fresh|reset)/',
            '/db:wipe/',
            '/Schema::(drop|dropIfExists|dropAllTables)\b/',
            '/DB::(unprepared)\b/',
        ], []);

        $this->assertSame([], $violations);
    }

    public function test_urls_and_hosts_are_not_hardcoded_in_application_code(): void
    {
        $violations = $this->filesMatching([
            '/[\'"]https?:\/\/(localhost|127\.0\.0\.1|[a-z0-9.-]+\.(com|id|co\.id|io|net|org))\b/i',
        ], []);

        $this->assertSame([], $violations, 'Gunakan env (APP_URL, FRONTEND_URL, QR_BASE_URL), bukan URL tetap.');
    }
}
