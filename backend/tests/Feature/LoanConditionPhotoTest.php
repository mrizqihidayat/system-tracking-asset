<?php

namespace Tests\Feature;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\FileType;
use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\Loan;
use App\Models\User;
use App\Services\AssetEventService;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Foto kondisi saat Terima kembali (T36): wajib jika Rusak Ringan/Rusak Berat, opsional jika Baik, disimpan per
 * peminjaman, dan menjadi "foto kondisi terakhir" aset tanpa menghapus foto peminjaman sebelumnya.
 */
class LoanConditionPhotoTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(config('filesystems.asset_disk'));
        $this->staff = User::factory()->create(['name' => 'Staf SHD']);
        $this->actingAs($this->staff);
    }

    private function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.asset_disk'));
    }

    private function loan(?Asset $asset = null): Loan
    {
        $asset ??= Asset::factory()->create(['condition' => AssetCondition::Baik, 'status' => AssetStatus::Tersedia]);
        $asset->forceFill(['created_at' => now()->subYear()])->save();

        $id = $this->postJson('/api/loans', [
            'asset_id' => $asset->id,
            'borrower_name' => 'Andi Pratama',
            'borrower_unit' => 'SGA',
            'purpose' => 'Pelatihan operator',
            'due_date' => today()->addDays(3)->toDateString(),
        ])->assertCreated()->json('data.id');

        return Loan::findOrFail($id);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function giveBack(Loan $loan, array $data, ?UploadedFile $photo = null)
    {
        return $this->patch("/api/loans/{$loan->id}/return", $data + ($photo ? ['condition_photo' => $photo] : []), ['Accept' => 'application/json']);
    }

    public function test_damaged_return_requires_a_photo(): void
    {
        $loan = $this->loan();

        foreach (['Rusak Ringan', 'Rusak Berat'] as $condition) {
            $this->giveBack($loan, ['return_condition' => $condition, 'note' => 'Retak'])
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['condition_photo' => 'Foto kondisi wajib diunggah jika kondisi tidak Baik.']);
        }

        $this->assertSame('Dipinjam', $loan->fresh()->status->value);
        $this->assertDatabaseCount('asset_files', 0);
    }

    public function test_good_condition_return_works_with_or_without_photo(): void
    {
        $withoutPhoto = $this->loan();
        $this->giveBack($withoutPhoto, ['return_condition' => 'Baik'])->assertOk()->assertJsonPath('data.condition_photo', null);

        $withPhoto = $this->loan();
        $this->giveBack($withPhoto, ['return_condition' => 'Baik'], UploadedFile::fake()->image('kondisi.jpg'))
            ->assertOk()
            ->assertJsonPath('data.condition_photo.loan_id', $withPhoto->id)
            ->assertJsonPath('data.condition_photo.file_type', 'Foto Kondisi');

        $this->assertDatabaseCount('asset_files', 1);
    }

    public function test_photo_is_stored_per_loan_and_shown_in_loan_resource(): void
    {
        $loan = $this->loan();

        $response = $this->giveBack($loan, ['return_condition' => 'Rusak Ringan', 'note' => 'Lecet'], UploadedFile::fake()->image('lecet.png'))->assertOk();

        $file = AssetFile::query()->where('loan_id', $loan->id)->firstOrFail();
        $this->assertSame($loan->asset_id, $file->asset_id);
        $this->assertSame(FileType::FotoKondisi, $file->file_type);
        $this->assertSame($this->staff->id, $file->uploaded_by);
        $this->disk()->assertExists($file->path);
        $response->assertJsonPath('data.condition_photo.id', $file->id);

        $this->getJson("/api/loans/{$loan->id}")->assertOk()->assertJsonPath('data.condition_photo.id', $file->id);
        $this->get("/api/asset-files/{$file->id}/download")->assertOk();
    }

    public function test_photos_of_earlier_loans_are_kept_and_latest_is_the_asset_condition_photo(): void
    {
        $asset = Asset::factory()->create(['condition' => AssetCondition::Baik, 'status' => AssetStatus::Tersedia]);

        $first = $this->loan($asset);
        $this->giveBack($first, ['return_condition' => 'Rusak Ringan', 'status' => 'Tersedia', 'note' => 'Lecet'], UploadedFile::fake()->image('satu.jpg'))->assertOk();
        $second = $this->loan($asset);
        $this->giveBack($second, ['return_condition' => 'Rusak Berat', 'status' => 'Perbaikan', 'note' => 'Patah'], UploadedFile::fake()->image('dua.jpg'))->assertOk();

        $firstFile = AssetFile::where('loan_id', $first->id)->firstOrFail();
        $secondFile = AssetFile::where('loan_id', $second->id)->firstOrFail();
        $this->disk()->assertExists($firstFile->path);
        $this->disk()->assertExists($secondFile->path);

        // Daftar file aset: yang terbaru di depan, jadi menjadi "foto kondisi terakhir".
        $files = $this->getJson("/api/assets/{$asset->id}/files?file_type=Foto Kondisi")->assertOk()->json('data');
        $this->assertSame([$secondFile->id, $firstFile->id], array_column($files, 'id'));
    }

    public function test_manual_condition_photo_upload_does_not_replace_loan_photos(): void
    {
        $loan = $this->loan();
        $this->giveBack($loan, ['return_condition' => 'Rusak Ringan', 'status' => 'Tersedia', 'note' => 'Lecet'], UploadedFile::fake()->image('lecet.jpg'))->assertOk();
        $loanFile = AssetFile::where('loan_id', $loan->id)->firstOrFail();

        $upload = fn () => $this->postJson("/api/assets/{$loan->asset_id}/files", ['file_type' => 'Foto Kondisi', 'file' => UploadedFile::fake()->image('manual.jpg')]);
        $upload()->assertCreated()->assertJsonPath('meta.replaced', false);
        // Unggah manual kedua menggantikan unggahan manual pertama saja.
        $upload()->assertCreated()->assertJsonPath('meta.replaced', true);

        $this->assertDatabaseHas('asset_files', ['id' => $loanFile->id]);
        $this->disk()->assertExists($loanFile->path);
        $this->assertSame(1, AssetFile::whereNull('loan_id')->where('file_type', FileType::FotoKondisi)->count());
    }

    public function test_loan_photo_cannot_be_deleted_manually(): void
    {
        $loan = $this->loan();
        $this->giveBack($loan, ['return_condition' => 'Rusak Ringan', 'status' => 'Tersedia', 'note' => 'Lecet'], UploadedFile::fake()->image('lecet.jpg'))->assertOk();
        $file = AssetFile::where('loan_id', $loan->id)->firstOrFail();

        $this->deleteJson("/api/asset-files/{$file->id}")->assertUnprocessable();

        $this->assertDatabaseHas('asset_files', ['id' => $file->id]);
        $this->disk()->assertExists($file->path);
    }

    public function test_photo_must_be_an_image_within_the_size_limit(): void
    {
        $loan = $this->loan();
        $data = ['return_condition' => 'Rusak Ringan', 'status' => 'Tersedia', 'note' => 'Lecet'];

        $this->giveBack($loan, $data, UploadedFile::fake()->create('kondisi.pdf', 100, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition_photo' => 'Foto harus berupa JPG, PNG, atau WebP.']);
        $this->giveBack($loan, $data, UploadedFile::fake()->create('kondisi.jpg', 6 * 1024, 'image/jpeg'))
            ->assertStatus(413)
            ->assertJsonPath('message', 'Ukuran file maksimal 5 MB.');

        $this->assertSame('Dipinjam', $loan->fresh()->status->value);
        $this->assertDatabaseCount('asset_files', 0);
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_lost_result_does_not_accept_a_photo(): void
    {
        $loan = $this->loan();

        $this->giveBack($loan, ['result' => 'hilang', 'note' => 'Hilang di lokasi'], UploadedFile::fake()->image('x.jpg'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['condition_photo' => 'Foto kondisi tidak diunggah jika aset hilang.']);

        $this->assertDatabaseCount('asset_files', 0);
    }

    public function test_failed_return_after_storing_the_photo_removes_the_file(): void
    {
        $loan = $this->loan();

        // Foto sudah tersimpan (file + baris), lalu pencatatan riwayat melempar error: seluruh transaksi dibatalkan
        // dan file di storage ikut dihapus, tidak ada file yatim.
        $this->mock(AssetEventService::class, function ($mock) {
            $mock->shouldReceive('record')->andThrow(new \RuntimeException('gagal mencatat riwayat'));
        });

        $this->withoutExceptionHandling();
        try {
            $this->giveBack($loan, ['return_condition' => 'Baik'], UploadedFile::fake()->image('kondisi.jpg'));
            $this->fail('Seharusnya melempar error.');
        } catch (\RuntimeException $e) {
            $this->assertSame('gagal mencatat riwayat', $e->getMessage());
        }

        $this->assertSame('Dipinjam', $loan->fresh()->status->value);
        $this->assertDatabaseCount('asset_files', 0);
        $this->assertSame([], $this->disk()->allFiles());
    }

    public function test_hard_deleting_the_asset_removes_loan_photos(): void
    {
        $loan = $this->loan();
        $this->giveBack($loan, ['return_condition' => 'Rusak Ringan', 'status' => 'Tersedia', 'note' => 'Lecet'], UploadedFile::fake()->image('lecet.jpg'))->assertOk();
        $file = AssetFile::where('loan_id', $loan->id)->firstOrFail();
        $asset = Asset::findOrFail($loan->asset_id);

        $this->deleteJson("/api/assets/{$asset->id}", ['reason' => 'Salah input', 'confirm_code' => $asset->code])->assertOk();

        $this->assertDatabaseMissing('asset_files', ['id' => $file->id]);
        $this->disk()->assertMissing($file->path);
    }
}
