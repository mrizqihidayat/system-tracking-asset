<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Services\MasterDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    public function __construct(private MasterDataService $master) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = $this->applySearch(Category::query(), $request->query('search'))
            ->withCount('assetTypes')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated())->loadCount('assetTypes');

        return CategoryResource::make($category)->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return CategoryResource::make($category->loadCount('assetTypes'));
    }

    public function destroy(Category $category): JsonResponse
    {
        $name = $category->name;
        $this->master->deleteCategory($category);

        return response()->json(['message' => "Kategori {$name} dihapus."]);
    }
}
