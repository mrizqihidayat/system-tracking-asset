<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesMasterList;
use App\Http\Requests\StoreConsumableCategoryRequest;
use App\Http\Requests\UpdateConsumableCategoryRequest;
use App\Http\Resources\ConsumableCategoryResource;
use App\Models\ConsumableCategory;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ConsumableCategoryController extends Controller
{
    use CachesMasterList;

    public function __construct(private MasterDataService $master) {}

    public function index(Request $request): JsonResponse
    {
        return $this->masterList($request, 'consumable-categories', fn () => ConsumableCategoryResource::collection(
            $this->applySearch(ConsumableCategory::query(), $request->query('search'))
                ->withCount('consumables')
                ->orderBy('name')
                ->get()
        )->resolve($request));
    }

    public function store(StoreConsumableCategoryRequest $request): JsonResponse
    {
        $category = ConsumableCategory::create($request->validated())->loadCount('consumables');

        return ConsumableCategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(UpdateConsumableCategoryRequest $request, ConsumableCategory $consumableCategory): ConsumableCategoryResource
    {
        $consumableCategory->update($request->validated());

        return ConsumableCategoryResource::make($consumableCategory->loadCount('consumables'));
    }

    /**
     * Pemakaian sebelum hapus (popup Data Master): rincian, total, can_delete.
     */
    public function usage(ConsumableCategory $consumableCategory): JsonResponse
    {
        return response()->json(['data' => $this->master->usage($consumableCategory)]);
    }

    public function destroy(ConsumableCategory $consumableCategory): JsonResponse
    {
        $name = $consumableCategory->name;
        $this->master->deleteConsumableCategory($consumableCategory);

        return response()->json(['message' => "Kategori stok {$name} dihapus."]);
    }
}
