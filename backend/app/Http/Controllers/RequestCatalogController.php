<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexRequestCatalogRequest;
use App\Http\Requests\StoreRequestCatalogRequest;
use App\Http\Requests\UpdateRequestCatalogRequest;
use App\Http\Resources\RequestCatalogResource;
use App\Models\RequestCatalog;
use App\Services\SupplyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class RequestCatalogController extends Controller
{
    public function __construct(private SupplyRequestService $requests) {}

    public function index(IndexRequestCatalogRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = RequestCatalog::query()
            ->with('providerDepartment')
            ->when($filters['provider_department_id'] ?? null, fn ($query, $id) => $query->where('provider_department_id', $id));

        $items = $this->applySearch($query, $filters['search'] ?? null, 'item_name')
            ->orderBy('item_name')
            ->get();

        return RequestCatalogResource::collection($items);
    }

    public function store(StoreRequestCatalogRequest $request): JsonResponse
    {
        $item = RequestCatalog::create($request->validated())->load('providerDepartment');

        return RequestCatalogResource::make($item)->response()->setStatusCode(201);
    }

    public function update(UpdateRequestCatalogRequest $request, RequestCatalog $requestCatalog): RequestCatalogResource
    {
        $requestCatalog->update($request->validated());

        return RequestCatalogResource::make($requestCatalog->load('providerDepartment'));
    }

    /**
     * Ditolak 422 bila masih dipakai permintaan, stok, atau standar ruangan.
     */
    public function destroy(RequestCatalog $requestCatalog): JsonResponse
    {
        $name = $requestCatalog->item_name;
        $this->requests->deleteCatalog($requestCatalog);

        return response()->json(['message' => "Item katalog {$name} dihapus."]);
    }
}
