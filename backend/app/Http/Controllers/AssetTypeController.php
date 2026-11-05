<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesMasterList;
use App\Http\Requests\StoreAssetTypeRequest;
use App\Http\Requests\UpdateAssetTypeRequest;
use App\Http\Resources\AssetTypeResource;
use App\Models\AssetType;
use App\Services\CodeGenerator;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use LogicException;

class AssetTypeController extends Controller
{
    use CachesMasterList;

    public function __construct(private CodeGenerator $codes, private MasterDataService $master) {}

    public function index(Request $request): JsonResponse
    {
        return $this->masterList($request, 'asset-types', fn () => AssetTypeResource::collection(
            $this->applySearch(AssetType::query(), $request->query('search'))
                ->orderBy('name')
                ->get()
        )->resolve($request));
    }

    /**
     * Kode yang akan didapat aset berikutnya pada jenis ini, tanpa memakai nomornya (tidak di-cache).
     */
    public function nextCode(AssetType $assetType): JsonResponse
    {
        return response()->json(['data' => ['next_code' => $this->codes->previewAssetCode($assetType)]]);
    }

    /**
     * Kode kosong → dibuat otomatis dari nama (SPEC §4).
     */
    public function store(StoreAssetTypeRequest $request): JsonResponse
    {
        $data = $request->validated();

        try {
            $data['code'] ??= $this->codes->assetTypeCode($data['name']);
        } catch (LogicException) {
            throw ValidationException::withMessages(['code' => 'Kode singkat tidak dapat dibuat dari nama ini, isi kode secara manual.']);
        }

        $assetType = AssetType::create($data);

        return AssetTypeResource::make($assetType)->response()->setStatusCode(201);
    }

    /**
     * Kode aset yang sudah ada tidak ikut berubah (label QR tercetak tetap berlaku).
     */
    public function update(UpdateAssetTypeRequest $request, AssetType $assetType): AssetTypeResource
    {
        $assetType->update($request->validated());

        return AssetTypeResource::make($assetType);
    }

    /**
     * Pemakaian sebelum hapus (popup Data Master): rincian, total, can_delete.
     */
    public function usage(AssetType $assetType): JsonResponse
    {
        return response()->json(['data' => $this->master->usage($assetType)]);
    }

    public function destroy(AssetType $assetType): JsonResponse
    {
        $name = $assetType->name;
        $this->master->deleteAssetType($assetType);

        return response()->json(['message' => "Jenis aset {$name} dihapus."]);
    }
}
