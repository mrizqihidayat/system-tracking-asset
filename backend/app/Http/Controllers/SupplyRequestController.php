<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Requests\IndexSupplyRequestRequest;
use App\Http\Requests\StoreSupplyRequestRequest;
use App\Http\Requests\UpdateSupplyRequestRequest;
use App\Http\Requests\UpdateSupplyRequestStatusRequest;
use App\Http\Resources\SupplyRequestResource;
use App\Models\SupplyRequest;
use App\Services\SupplyRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SupplyRequestController extends Controller
{
    public function __construct(private SupplyRequestService $requests) {}

    public function index(IndexSupplyRequestRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = SupplyRequest::query()->with(SupplyRequestResource::RELATIONS)->filter($filters);

        $requests = $query->listOrder()
            ->paginate($filters['per_page'] ?? IndexSupplyRequestRequest::DEFAULT_PER_PAGE)
            ->withQueryString();

        return SupplyRequestResource::collection($requests);
    }

    public function store(StoreSupplyRequestRequest $request): JsonResponse
    {
        $supplyRequest = $this->requests->create($request->validated(), $request->user());

        return SupplyRequestResource::make($supplyRequest->load(SupplyRequestResource::RELATIONS))
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateSupplyRequestRequest $request, SupplyRequest $supplyRequest): SupplyRequestResource
    {
        $supplyRequest = $this->requests->update($supplyRequest, $request->validated());

        return SupplyRequestResource::make($supplyRequest->load(SupplyRequestResource::RELATIONS));
    }

    public function updateStatus(UpdateSupplyRequestStatusRequest $request, SupplyRequest $supplyRequest): SupplyRequestResource
    {
        $data = $request->validated();

        $supplyRequest = $this->requests->changeStatus($supplyRequest, RequestStatus::from($data['status']), $data['note'] ?? null);

        return SupplyRequestResource::make($supplyRequest->load(SupplyRequestResource::RELATIONS));
    }

    /**
     * Saran permintaan per unit penyedia (kekurangan ruangan + stok Menipis/Habis).
     */
    public function suggestions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->requests->suggestions()]);
    }
}
