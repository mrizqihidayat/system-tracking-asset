<?php

namespace App\Services;

use App\Enums\FileType;
use App\Models\Asset;
use App\Models\AssetFile;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Satu-satunya jalur tulis/hapus file aset (SPEC §5). Foto Aset, Foto Kondisi, BAST, Garansi = satu slot
 * per aset (unggahan baru mengganti); Lainnya menumpuk. File storage dihapus setelah commit.
 */
class AssetFileService
{
    /**
     * Jenis yang hanya boleh satu per aset.
     *
     * @return list<FileType>
     */
    public static function slotTypes(): array
    {
        return [FileType::FotoAset, FileType::FotoKondisi, FileType::Bast, FileType::Garansi];
    }

    public static function isSlot(FileType $type): bool
    {
        return in_array($type, self::slotTypes(), true);
    }

    public function disk(): Filesystem
    {
        return Storage::disk(config('filesystems.asset_disk'));
    }

    /**
     * @return array{file: AssetFile, replaced: bool}
     */
    public function store(Asset $asset, FileType $type, UploadedFile $upload, User $user): array
    {
        $extension = $upload->guessExtension() ?: strtolower($upload->getClientOriginalExtension());
        $path = $this->disk()->putFileAs("assets/{$asset->id}", $upload, Str::uuid().'.'.$extension);

        if ($path === false) {
            throw new RuntimeException('Gagal menyimpan file.');
        }

        $attributes = [
            'asset_id' => $asset->id,
            'file_type' => $type,
            'path' => $path,
            'original_name' => $upload->getClientOriginalName(),
            'mime_type' => $upload->getMimeType(),
            'size' => $upload->getSize(),
            'uploaded_by' => $user->id,
        ];

        try {
            return DB::transaction(function () use ($asset, $type, $attributes) {
                Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();

                $old = self::isSlot($type)
                    ? AssetFile::query()->where('asset_id', $asset->id)->where('file_type', $type->value)->get()
                    : collect();

                if ($old->isNotEmpty()) {
                    AssetFile::query()->whereKey($old->modelKeys())->delete();
                    $this->deleteFromStorageAfterCommit($old->pluck('path')->all());
                }

                return ['file' => AssetFile::create($attributes), 'replaced' => $old->isNotEmpty()];
            });
        } catch (Throwable $e) {
            $this->disk()->delete($path);

            throw $e;
        }
    }

    public function delete(AssetFile $file): void
    {
        DB::transaction(function () use ($file) {
            $file = AssetFile::query()->whereKey($file->getKey())->lockForUpdate()->firstOrFail();
            $file->delete();

            $this->deleteFromStorageAfterCommit([$file->path]);
        });
    }

    /**
     * @param  list<string>  $paths
     */
    public function deleteFromStorageAfterCommit(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        DB::afterCommit(fn () => $this->disk()->delete($paths));
    }
}
