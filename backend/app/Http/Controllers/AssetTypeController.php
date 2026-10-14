<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreAssetTypeRequest;
use App\Http\Requests\UpdateAssetTypeRequest;
use App\Http\Resources\AssetTypeResource;
use App\Models\AssetType;
use App\Services\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetTypeController extends Controller
{
    public function __construct(private CodeGenerator $codes) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $request->validate(['category_id' => ['nullable', 'integer']]);

        $assetTypes = $this->applySearch(AssetType::query(), $request->query('search'))
            ->with('category')
            ->when($request->query('category_id'), fn ($query, $categoryId) => $query->where('category_id', $categoryId))
            ->orderBy('name')
            ->get();

        return AssetTypeResource::collection($assetTypes);
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
}
