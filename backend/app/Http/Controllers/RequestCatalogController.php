<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\CachesMasterList;
use App\Http\Requests\IndexRequestCatalogRequest;
use App\Http\Requests\StoreRequestCatalogRequest;
use App\Http\Requests\UpdateRequestCatalogRequest;
use App\Http\Resources\RequestCatalogResource;
use App\Models\RequestCatalog;
use App\Services\SupplyRequestService;
use Illuminate\Http\JsonResponse;

class RequestCatalogController extends Controller
{
    use CachesMasterList;

    public function __construct(private SupplyRequestService $requests) {}

    public function index(IndexRequestCatalogRequest $request): JsonResponse
    {
        $filters = $request->validated();
        $departmentId = (int) ($filters['provider_department_id'] ?? 0);

        return $this->masterList($request, "request-catalog:{$departmentId}", fn () => RequestCatalogResource::collection(
            $this->applySearch(RequestCatalog::query(), $filters['search'] ?? null, 'item_name')
                ->with('providerDepartment')
                ->when($departmentId, fn ($query) => $query->where('provider_department_id', $departmentId))
                ->orderBy('item_name')
                ->get()
        )->resolve($request));
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
