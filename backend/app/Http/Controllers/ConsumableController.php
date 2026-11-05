<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\PaginatesList;
use App\Http\Requests\IndexConsumableRequest;
use App\Http\Requests\StoreConsumableRequest;
use App\Http\Requests\StoreStockTransactionRequest;
use App\Http\Requests\UpdateConsumableRequest;
use App\Http\Resources\ConsumableResource;
use App\Http\Resources\StockTransactionResource;
use App\Models\Consumable;
use App\Services\ConsumableService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ConsumableController extends Controller
{
    use PaginatesList;

    private const RELATIONS = ['providerDepartment', 'consumableCategory', 'room.building', 'openRequest'];

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
        $consumable = $this->consumables->create($request->validated(), $request->user())->load(self::RELATIONS);

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
        return response()->json(['data' => [
            'rooms' => $this->consumables->usage($consumable),
            'delete_block_reason' => $this->consumables->deleteBlockReason($consumable),
        ]]);
    }

    public function update(UpdateConsumableRequest $request, Consumable $consumable): ConsumableResource
    {
        $consumable = $this->consumables->update($consumable, $request->validated());

        return ConsumableResource::make($consumable->load(self::RELATIONS));
    }

    /**
     * Kartu stok: riwayat transaksi satu barang, terbaru lebih dulu.
     */
    public function transactions(Request $request, Consumable $consumable): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        return StockTransactionResource::collection(
            $consumable->transactions()->with(['room.building', 'creator'])->orderByDesc('id')->paginate($request->integer('per_page', 20))->withQueryString(),
        );
    }

    /**
     * Catat transaksi stok; respons berisi transaksi dan data barang terbaru.
     */
    public function storeTransaction(StoreStockTransactionRequest $request, Consumable $consumable): JsonResponse
    {
        $transaction = $this->consumables->transact($consumable, $request->validated(), $request->user());

        return StockTransactionResource::make($transaction->load(['room.building', 'creator']))
            ->additional(['meta' => ['consumable' => ConsumableResource::make($consumable->refresh()->load(self::RELATIONS))]])
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Hapus permanen (hanya jika transaksinya saldo awal saja); baris standar ikut terhapus, daftar ruangan dikembalikan.
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
