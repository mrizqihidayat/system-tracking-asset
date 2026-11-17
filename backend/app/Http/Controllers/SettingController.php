<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateSettingsRequest;
use App\Services\SettingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Pengaturan aplikasi (T40, SPEC §15). Bagian publik (tanpa login) hanya berisi tampilan & teks halaman login;
 * baca lengkap, simpan, dan unggah logo/favicon khusus akun pegawai SHD (middleware employee.account).
 */
class SettingController extends Controller
{
    public function __construct(private SettingService $settings) {}

    public function publicIndex(): JsonResponse
    {
        return response()->json(['data' => $this->settings->publicPayload()]);
    }

    public function index(): JsonResponse
    {
        return $this->full();
    }

    public function update(UpdateSettingsRequest $request): JsonResponse
    {
        $this->settings->update($request->settings(), $request->user(), $request->ip());

        return $this->full();
    }

    public function storeFile(Request $request, string $type): JsonResponse
    {
        $request->validate(
            ['file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:1024']],
            [
                'file.required' => 'Pilih file gambar.',
                'file.file' => 'Pilih file gambar.',
                'file.uploaded' => 'Ukuran gambar maksimal 1 MB.',
                'file.mimes' => 'Gambar harus PNG, JPG, atau WebP.',
                'file.max' => 'Ukuran gambar maksimal 1 MB.',
            ],
        );

        $this->settings->storeFile($type, $request->file('file'), $request->user(), $request->ip());

        return $this->full();
    }

    public function destroyFile(Request $request, string $type): JsonResponse
    {
        $this->settings->deleteFile($type, $request->user(), $request->ip());

        return $this->full();
    }

    /** Logo/favicon untuk halaman login dan tab browser: publik, hanya gambar yang diunggah lewat Pengaturan. */
    public function file(string $type): StreamedResponse
    {
        $path = $this->settings->filePath($type);
        abort_if($path === null, 404);

        return $this->settings->disk()->response($path, null, [
            'Cache-Control' => 'public, max-age=31536000, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function full(): JsonResponse
    {
        return response()->json([
            'data' => $this->settings->editable(),
            'defaults' => $this->settings->defaults(),
            'last_change' => $this->settings->lastChange(),
        ]);
    }
}
