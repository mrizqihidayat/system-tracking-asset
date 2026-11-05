<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesList;
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
    use PaginatesList;

    private const RELATIONS = ['providerDepartment', 'consumableCategory', 'openRequest'];

    public function __construct(private ConsumableService $consumables) {}

    public function index(IndexConsumableRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        // category_name dipakai sort=category.
        $query = Consumable::query()->with(self::RELATIONS)->withAggregate('consumableCategory as category_name', 'name')->filter($filters);

        $consumables = $this->paginateList(
            $query, $filters, IndexConsumableRequest::SORTABLE, fn ($query) => $query->orderBy('name'), IndexConsumableRequest::DEFAULT_PER_PAGE,
        );

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
        $consumable = $this->consumables->update($consumable, $request->validated());

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
