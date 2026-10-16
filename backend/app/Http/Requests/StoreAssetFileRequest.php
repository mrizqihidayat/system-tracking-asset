<?php

namespace App\Http\Requests;

use App\Enums\FileType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAssetFileRequest extends FormRequest
{
    public const MAX_BYTES = 5 * 1024 * 1024;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * Terlalu besar → 413 (SPEC §14), termasuk ditolak PHP (upload_max_filesize) sebelum validasi.
     */
    protected function prepareForValidation(): void
    {
        $file = $this->file('file');

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
            'file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'mimetypes:image/jpeg,image/png,image/webp,application/pdf'],
        ];
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
