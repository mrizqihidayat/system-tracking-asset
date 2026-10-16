<?php

namespace Tests\Feature;

use App\Enums\FileType;
use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AssetFileTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Asset $asset;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.asset_disk'));
        $this->user = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->user);
        $this->asset = Asset::factory()->create();
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.asset_disk'));
    }

    private function upload(string $type, ?UploadedFile $file = null, ?Asset $asset = null)
    {
        $asset ??= $this->asset;

        return $this->postJson("/api/assets/{$asset->id}/files", [
            'file_type' => $type,
            'file' => $file ?? UploadedFile::fake()->image('foto.jpg', 200, 200),
        ]);
    }

    public function test_endpoints_require_token(): void
    {
        $this->app['auth']->forgetGuards();
        $file = AssetFile::factory()->create();

        $this->getJson("/api/assets/{$this->asset->id}/files")->assertUnauthorized();
        $this->postJson("/api/assets/{$this->asset->id}/files")->assertUnauthorized();
        $this->deleteJson("/api/asset-files/{$file->id}")->assertUnauthorized();
        $this->getJson("/api/asset-files/{$file->id}/download")->assertUnauthorized();
    }

    public function test_upload_stores_file_and_metadata(): void
    {
        $response = $this->upload('Foto Aset')->assertCreated()
            ->assertJsonPath('data.file_type', 'Foto Aset')
            ->assertJsonPath('data.original_name', 'foto.jpg')
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.uploaded_by.name', 'Staf SHD')
            ->assertJsonPath('meta.replaced', false);

        $file = AssetFile::query()->findOrFail($response->json('data.id'));

        $this->assertSame($this->asset->id, $file->asset_id);
        $this->assertSame($this->user->id, $file->uploaded_by);
        $this->assertStringStartsWith("assets/{$this->asset->id}/", $file->path);
        $this->assertStringNotContainsString('foto', $file->path);
        $this->disk()->assertExists($file->path);
        $this->assertStringEndsWith("/api/asset-files/{$file->id}/download", $response->json('data.download_url'));
    }

    public function test_pdf_is_accepted_for_every_type(): void
    {
        foreach (FileType::values() as $type) {
            $this->upload($type, UploadedFile::fake()->create('dok.pdf', 100, 'application/pdf'))->assertCreated();
        }

        $this->assertSame(5, $this->asset->files()->count());
    }

    public function test_slot_type_replaces_previous_file(): void
    {
        foreach (['Foto Aset', 'Foto Kondisi', 'BAST', 'Garansi'] as $type) {
            $first = $this->upload($type, UploadedFile::fake()->create('lama.pdf', 10, 'application/pdf'))->assertCreated();
            $oldPath = AssetFile::query()->findOrFail($first->json('data.id'))->path;

            $second = $this->upload($type, UploadedFile::fake()->create('baru.pdf', 10, 'application/pdf'))
                ->assertCreated()
                ->assertJsonPath('meta.replaced', true);

            $this->assertDatabaseMissing('asset_files', ['id' => $first->json('data.id')]);
            $this->disk()->assertMissing($oldPath);
            $this->disk()->assertExists(AssetFile::query()->findOrFail($second->json('data.id'))->path);
            $this->assertSame(1, $this->asset->files()->where('file_type', $type)->count());
        }
    }

    public function test_slot_replacement_does_not_touch_other_assets_or_types(): void
    {
        $other = Asset::factory()->create();
        $otherAsset = $this->upload('Foto Aset', asset: $other)->assertCreated();
        $otherType = $this->upload('BAST', UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'))->assertCreated();

        $this->upload('Foto Aset')->assertCreated()->assertJsonPath('meta.replaced', false);
        $this->upload('Foto Aset')->assertCreated()->assertJsonPath('meta.replaced', true);

        $this->assertDatabaseHas('asset_files', ['id' => $otherAsset->json('data.id')]);
        $this->assertDatabaseHas('asset_files', ['id' => $otherType->json('data.id')]);
    }

    public function test_lainnya_stacks_and_keeps_original_names(): void
    {
        $this->upload('Lainnya', UploadedFile::fake()->create('Surat Jalan.pdf', 10, 'application/pdf'))->assertCreated()->assertJsonPath('meta.replaced', false);
        $this->upload('Lainnya', UploadedFile::fake()->create('Invoice 2026.pdf', 10, 'application/pdf'))->assertCreated()->assertJsonPath('meta.replaced', false);
        $this->upload('Lainnya', UploadedFile::fake()->image('Foto Servis.png'))->assertCreated();

        $names = $this->asset->files()->where('file_type', 'Lainnya')->orderBy('id')->pluck('original_name')->all();

        $this->assertSame(['Surat Jalan.pdf', 'Invoice 2026.pdf', 'Foto Servis.png'], $names);
        $this->assertCount(3, $this->disk()->allFiles("assets/{$this->asset->id}"));
    }

    public function test_list_orders_slots_first_then_lainnya_newest_first(): void
    {
        $lain1 = $this->upload('Lainnya', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->json('data.id');
        $garansi = $this->upload('Garansi', UploadedFile::fake()->create('g.pdf', 10, 'application/pdf'))->json('data.id');
        $lain2 = $this->upload('Lainnya', UploadedFile::fake()->create('b.pdf', 10, 'application/pdf'))->json('data.id');
        $foto = $this->upload('Foto Aset')->json('data.id');

        $this->getJson("/api/assets/{$this->asset->id}/files")
            ->assertOk()
            ->assertJsonPath('data.*.id', [$foto, $garansi, $lain2, $lain1]);

        $this->getJson("/api/assets/{$this->asset->id}/files?file_type=Lainnya")->assertOk()->assertJsonCount(2, 'data');
        $this->getJson("/api/assets/{$this->asset->id}/files?file_type=Salah")->assertUnprocessable();
    }

    public function test_delete_removes_row_and_storage_file_for_every_type(): void
    {
        foreach (['Foto Aset', 'Lainnya'] as $type) {
            $id = $this->upload($type)->json('data.id');
            $path = AssetFile::query()->findOrFail($id)->path;

            $this->deleteJson("/api/asset-files/{$id}")->assertOk();

            $this->assertDatabaseMissing('asset_files', ['id' => $id]);
            $this->disk()->assertMissing($path);
        }

        $this->deleteJson('/api/asset-files/999999')->assertNotFound();
    }

    public function test_download_streams_file_inline_or_attachment(): void
    {
        $id = $this->upload('Lainnya', UploadedFile::fake()->create('Surat Jalan.pdf', 10, 'application/pdf'))->json('data.id');

        $inline = $this->get("/api/asset-files/{$id}/download")->assertOk();
        $this->assertStringContainsString('inline', $inline->headers->get('Content-Disposition'));
        $this->assertStringContainsString('Surat Jalan.pdf', $inline->headers->get('Content-Disposition'));
        $this->assertSame('application/pdf', $inline->headers->get('Content-Type'));

        $attachment = $this->get("/api/asset-files/{$id}/download?download=1")->assertOk();
        $this->assertStringContainsString('attachment', $attachment->headers->get('Content-Disposition'));
    }

    public function test_download_returns_404_when_physical_file_is_missing(): void
    {
        $file = AssetFile::factory()->create(['asset_id' => $this->asset->id]);

        $this->getJson("/api/asset-files/{$file->id}/download")->assertNotFound()->assertJsonPath('message', 'File tidak ditemukan.');
        $this->getJson('/api/asset-files/999999/download')->assertNotFound();
    }

    public function test_validation_rejects_bad_type_and_missing_fields(): void
    {
        $this->upload('Salah')->assertUnprocessable()->assertJsonValidationErrors('file_type');
        $this->upload('Lainnya', UploadedFile::fake()->create('virus.exe', 10, 'application/x-msdownload'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->upload('Lainnya', UploadedFile::fake()->create('catatan.txt', 10, 'text/plain'))->assertUnprocessable()->assertJsonValidationErrors('file');
        $this->postJson("/api/assets/{$this->asset->id}/files", [])->assertUnprocessable()->assertJsonValidationErrors(['file_type', 'file']);

        $this->assertSame(0, $this->asset->files()->count());
    }

    public function test_file_over_5_mb_returns_413_and_keeps_existing_file(): void
    {
        $id = $this->upload('Foto Aset')->json('data.id');

        $this->upload('Foto Aset', UploadedFile::fake()->create('besar.pdf', 5121, 'application/pdf'))
            ->assertStatus(413)
            ->assertJsonPath('message', 'Ukuran file maksimal 5 MB.');

        $this->upload('Foto Aset', UploadedFile::fake()->create('pas.pdf', 5120, 'application/pdf'))->assertCreated();

        $this->assertDatabaseMissing('asset_files', ['id' => $id]);
        $this->assertSame(1, $this->asset->files()->where('file_type', 'Foto Aset')->count());
    }

    public function test_unknown_asset_returns_404(): void
    {
        $this->postJson('/api/assets/999999/files', ['file_type' => 'Lainnya', 'file' => UploadedFile::fake()->image('a.jpg')])->assertNotFound();
        $this->getJson('/api/assets/999999/files')->assertNotFound();
    }

    public function test_failed_replacement_does_not_leave_orphan_file(): void
    {
        $id = $this->upload('Foto Aset')->json('data.id');
        $oldPath = AssetFile::query()->findOrFail($id)->path;
        $before = $this->disk()->allFiles('assets');

        AssetFile::creating(fn () => throw new \RuntimeException('gagal simpan'));

        try {
            $this->withoutExceptionHandling()->upload('Foto Aset');
            $this->fail('Seharusnya melempar exception.');
        } catch (\RuntimeException $e) {
            $this->assertSame('gagal simpan', $e->getMessage());
        } finally {
            AssetFile::flushEventListeners();
        }

        $this->assertDatabaseHas('asset_files', ['id' => $id]);
        $this->disk()->assertExists($oldPath);
        $this->assertEqualsCanonicalizing($before, $this->disk()->allFiles('assets'));
    }

    public function test_deleting_asset_removes_its_files_from_storage(): void
    {
        $this->upload('Foto Aset')->assertCreated();
        $this->upload('Lainnya', UploadedFile::fake()->create('a.pdf', 10, 'application/pdf'))->assertCreated();
        $paths = $this->asset->files()->pluck('path');
        $this->assertCount(2, $paths);

        $this->deleteJson("/api/assets/{$this->asset->id}")->assertOk();

        $this->assertSame(0, AssetFile::query()->where('asset_id', $this->asset->id)->count());
        foreach ($paths as $path) {
            $this->disk()->assertMissing($path);
        }
    }
}
