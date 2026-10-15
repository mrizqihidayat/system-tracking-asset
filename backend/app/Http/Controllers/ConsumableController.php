<?php

namespace App\Http\Controllers;

use App\Enums\ConsumableStatus;
use App\Http\Requests\IndexConsumableRequest;
use App\Http\Requests\StoreConsumableRequest;
use App\Http\Requests\UpdateConsumableQuantityRequest;
use App\Http\Requests\UpdateConsumableRequest;
use App\Http\Resources\ConsumableResource;
use App\Models\Consumable;
use App\Services\ConsumableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsumableController extends Controller
{
    private const RELATIONS = ['providerDepartment', 'catalog', 'room.building'];

    public function __construct(private ConsumableService $consumables) {}

    public function index(IndexConsumableRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Consumable::query()
            ->with(self::RELATIONS)
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->withStatus(ConsumableStatus::from($status)))
            ->when($filters['category'] ?? null, fn ($query, $category) => $query->whereRaw('lower(category) = ?', [mb_strtolower($category)]))
            ->when($filters['provider_department_id'] ?? null, fn ($query, $id) => $query->where('provider_department_id', $id))
            ->when($filters['room_id'] ?? null, fn ($query, $id) => $query->where('room_id', $id))
            ->when($filters['building_id'] ?? null, fn ($query, $id) => $query->whereHas('room', fn ($room) => $room->where('building_id', $id)));

        $this->applySearch($query, $filters['search'] ?? null, ['consumables.name', 'consumables.category']);

        $consumables = $query->orderBy('name')
            ->paginate($filters['per_page'] ?? IndexConsumableRequest::DEFAULT_PER_PAGE)
            ->withQueryString();

        return ConsumableResource::collection($consumables);
    }

    public function store(StoreConsumableRequest $request): JsonResponse
    {
        $consumable = Consumable::create($request->validated())->load(self::RELATIONS);

        return ConsumableResource::make($consumable)->response()->setStatusCode(201);
    }

    /**
     * Detail + meta.used_in_rooms ("Dipakai di ruangan: ...").
     */
    public function show(Consumable $consumable): ConsumableResource
    {
        return ConsumableResource::make($consumable->load(self::RELATIONS))
            ->additional(['meta' => ['used_in_rooms' => $this->consumables->usage($consumable)]]);
    }

    /**
     * Ruangan yang standarnya memakai barang ini (peringatan sebelum hapus).
     */
    public function usage(Consumable $consumable): JsonResponse
    {
        return response()->json(['data' => ['rooms' => $this->consumables->usage($consumable)]]);
    }

    public function update(UpdateConsumableRequest $request, Consumable $consumable): ConsumableResource
    {
        $consumable->update($request->validated());

        return ConsumableResource::make($consumable->load(self::RELATIONS));
    }

    /**
     * Ubah jumlah: quantity (set) atau adjustment (tambah/kurang), dengan lock; tidak boleh negatif.
     */
    public function updateQuantity(UpdateConsumableQuantityRequest $request, Consumable $consumable): ConsumableResource
    {
        $data = $request->validated();

        $consumable = isset($data['quantity'])
            ? $this->consumables->setQuantity($consumable, (int) $data['quantity'])
            : $this->consumables->adjust($consumable, (int) $data['adjustment']);

        return ConsumableResource::make($consumable->load(self::RELATIONS));
    }

    /**
     * Hapus permanen; tautan di standar ruangan dilepas (daftar ruangan dikembalikan).
     */
    public function destroy(Consumable $consumable): JsonResponse
    {
        $name = $consumable->name;
        $detached = $this->consumables->delete($consumable);

        return response()->json([
            'message' => "Barang {$name} dihapus.",
            'detached_rooms' => $detached,
        ]);
    }
}
