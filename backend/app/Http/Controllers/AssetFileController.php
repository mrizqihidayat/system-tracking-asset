<?php

namespace App\Http\Controllers;

use App\Enums\FileType;
use App\Http\Requests\StoreAssetFileRequest;
use App\Http\Resources\AssetFileResource;
use App\Models\Asset;
use App\Models\AssetFile;
use App\Services\AssetFileService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AssetFileController extends Controller
{
    public function __construct(private AssetFileService $files) {}

    /**
     * Slot (Foto Aset, Foto Kondisi, BAST, Garansi) dulu sesuai urutan, lalu Lainnya terbaru dulu.
     */
    public function index(Request $request, Asset $asset): AnonymousResourceCollection
    {
        $request->validate(['file_type' => ['nullable', Rule::enum(FileType::class)]]);

        $order = array_flip(FileType::values());

        $files = $asset->files()
            ->with('uploader')
            ->when($request->query('file_type'), fn ($query, $type) => $query->where('file_type', $type))
            ->get()
            ->sortBy([
                fn (AssetFile $a, AssetFile $b) => $order[$a->file_type->value] <=> $order[$b->file_type->value],
                fn (AssetFile $a, AssetFile $b) => $b->id <=> $a->id,
            ])
            ->values();

        return AssetFileResource::collection($files);
    }

    /**
     * Slot terisi → diganti (meta.replaced = true); Lainnya → menumpuk.
     */
    public function store(StoreAssetFileRequest $request, Asset $asset): JsonResponse
    {
        $result = $this->files->store(
            $asset,
            FileType::from($request->validated('file_type')),
            $request->file('file'),
            $request->user(),
        );

        return AssetFileResource::make($result['file']->load('uploader'))
            ->additional(['meta' => ['replaced' => $result['replaced']]])
            ->response()
            ->setStatusCode(201);
    }

    public function destroy(AssetFile $assetFile): JsonResponse
    {
        $name = $assetFile->original_name;
        $this->files->delete($assetFile);

        return response()->json(['message' => "File {$name} dihapus."]);
    }

    /**
     * Stream lewat backend (disk private). Default inline; ?download=1 untuk unduh.
     */
    public function download(Request $request, AssetFile $assetFile): StreamedResponse|JsonResponse
    {
        $disk = $this->files->disk();

        if (! $disk->exists($assetFile->path)) {
            return response()->json(['message' => 'File tidak ditemukan.'], 404);
        }

        return $disk->response(
            $assetFile->path,
            $assetFile->original_name,
            ['Content-Type' => $assetFile->mime_type ?: 'application/octet-stream'],
            $request->boolean('download') ? 'attachment' : 'inline',
        );
    }
}
