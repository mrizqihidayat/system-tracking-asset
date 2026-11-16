<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAssetFileRequest extends FormRequest
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_NAME_LENGTH = 255;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Terlalu besar → 413 (SPEC §14), termasuk ditolak PHP (upload_max_filesize) sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        self::rejectIfTooLarge($this->file('file'));
    }

    /**
     * Dipakai juga oleh unggahan foto di request lain (mis. foto kondisi saat Terima kembali, T36).
     */
    public static function rejectIfTooLarge(mixed $file): void
    {
        if (! $file instanceof UploadedFile) {
            return;
        }

        $tooLarge = in_array($file->getError(), [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
            || ($file->isValid() && $file->getSize() > self::MAX_BYTES);

        if ($tooLarge) {
            throw new HttpResponseException(response()->json(['message' => 'Ukuran file maksimal 5 MB.'], 413));
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file_type' => ['required', Rule::enum(FileType::class)],
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf', self::validName()],
        ];
    }

    /**
     * Nama asli disimpan di kolom varchar(255) dan dikirim sebagai JSON: wajib UTF-8 sah dan maks. 255 karakter.
     */
    public static function validName(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $name = $value->getClientOriginalName();

            if (! mb_check_encoding($name, 'UTF-8') || mb_strlen($name, 'UTF-8') > self::MAX_NAME_LENGTH) {
                $fail('Nama file tidak valid atau lebih dari 255 karakter.');
            }
        };
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => 'File harus berupa JPG, PNG, WebP, atau PDF.',
            'file.mimetypes' => 'File harus berupa JPG, PNG, WebP, atau PDF.',
        ];
    }
}
