<?php

namespace App\Http\Controllers;

use App\Enums\RequestStatus;
use App\Http\Controllers\Concerns\PaginatesList;
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
    use PaginatesList;

    public function __construct(private SupplyRequestService $requests) {}

    public function index(IndexSupplyRequestRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = SupplyRequest::query()->with(SupplyRequestResource::RELATIONS)->filter($filters);

        $requests = $this->paginateList(
            $query, $filters, IndexSupplyRequestRequest::SORTABLE, fn ($query) => $query->listOrder(), IndexSupplyRequestRequest::DEFAULT_PER_PAGE,
        );

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

        $supplyRequest = $this->requests->changeStatus($supplyRequest, RequestStatus::from($data['status']), $data['note'] ?? null, $request->user(), $data['received_quantity'] ?? null);

        return SupplyRequestResource::make($supplyRequest->load(SupplyRequestResource::RELATIONS))
            ->additional(['meta' => ['impact' => $supplyRequest->statusImpact]]);
    }

    /**
     * Pratinjau dampak perubahan status ke stok (status tujuan lewat query).
     */
    public function statusImpact(UpdateSupplyRequestStatusRequest $request, SupplyRequest $supplyRequest): JsonResponse
    {
        return response()->json(['data' => $this->requests->statusImpact($supplyRequest, RequestStatus::from($request->validated('status')), $request->validated('received_quantity'))]);
    }

    /**
     * Saran permintaan per departemen penyedia (stok Menipis/Habis).
     */
    public function suggestions(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->requests->suggestions()]);
    }
}
