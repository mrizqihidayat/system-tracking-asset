<?php

namespace App\Models;

use App\Enums\FileType;
use Database\Factories\AssetFileFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['asset_id', 'loan_id', 'file_type', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by'])]
class AssetFile extends Model
{
    /** @use HasFactory<AssetFileFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /**
     * Nama untuk header unduhan: ekstensi mengikuti isi file (ekstensi path tersimpan), bukan nama dari pengunggah.
     * Nama asli tetap utuh di kolom original_name untuk ditampilkan.
     */
    public function downloadName(): string
    {
        $actual = strtolower(pathinfo($this->path, PATHINFO_EXTENSION));
        $given = strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
        $jpeg = ['jpg', 'jpeg'];

        if ($actual === '' || $given === $actual || (in_array($given, $jpeg, true) && in_array($actual, $jpeg, true))) {
            return $this->original_name;
        }

        return (pathinfo($this->original_name, PATHINFO_FILENAME) ?: 'file').'.'.$actual;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'file_type' => FileType::class,
            'size' => 'integer',
        ];
    }

    /**
     * Peminjaman asal file (foto kondisi saat Terima kembali, T36); null = file biasa di aset.
     *
     * @return BelongsTo<Loan, $this>
     */
    public function loan(): BelongsTo
    {
        return $this->belongsTo(Loan::class);
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
