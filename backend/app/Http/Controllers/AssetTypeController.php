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

class AssetTypeController extends Controller
{
    use CachesMasterList;

    public function __construct(private CodeGenerator $codes, private MasterDataService $master) {}

    public function index(Request $request): JsonResponse
    {
        $request->validate(['category_id' => ['nullable', 'integer']]);
        $categoryId = $request->integer('category_id');

        return $this->masterList($request, "asset-types:{$categoryId}", fn () => AssetTypeResource::collection(
            $this->applySearch(AssetType::query(), $request->query('search'))
                ->with('category')
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->orderBy('name')
                ->get()
        )->resolve($request));
    }

    /**
     * Kode kosong → dibuat otomatis dari nama (SPEC §4).
     */
    public function store(StoreAssetTypeRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['code'] ??= $this->codes->assetTypeCode($data['name']);

        $assetType = AssetType::create($data)->load('category');

        return AssetTypeResource::make($assetType)->response()->setStatusCode(201);
    }

    /**
     * Kode aset yang sudah ada tidak ikut berubah (label QR tercetak tetap berlaku).
     */
    public function update(UpdateAssetTypeRequest $request, AssetType $assetType): AssetTypeResource
    {
        $assetType->update($request->validated());

        return AssetTypeResource::make($assetType->load('category'));
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
