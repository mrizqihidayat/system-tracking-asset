<?php

namespace App\Http\Controllers;

use App\Enums\CountMethod;
use App\Http\Requests\AdjustRoomStockRequest;
use App\Http\Requests\FillFromStockRequest;
use App\Http\Requests\StoreRoomStandardRequest;
use App\Http\Resources\RoomResource;
use App\Http\Resources\RoomStandardResource;
use App\Http\Resources\RoomStockTransactionResource;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\RoomStockTransaction;
use App\Services\RoomReadinessService;
use App\Services\RoomStockService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * Kelengkapan ruangan (SPEC §8): aset di ruangan, standar per jenis/barang, catat perubahan, tandai sudah dicek, isi dari stok, kartu ruangan.
 */
class RoomStandardController extends Controller
{
    public function __construct(private RoomReadinessService $readiness, private RoomStockService $roomStock) {}

    public function checklist(Room $room): JsonResponse
    {
        return $this->checklistResponse($room);
    }

    public function store(StoreRoomStandardRequest $request, Room $room): JsonResponse
    {
        $data = $request->standardData();
        $standard = $room->standards()->create($data + $this->addedItemState($room->id, $data['consumable_id']));

        return RoomStandardResource::make($this->prepare($standard))->response()->setStatusCode(201);
    }

    /**
     * Ubah definisi standar; jumlah Ada & status cek tetap selama barangnya sama.
     */
    public function update(StoreRoomStandardRequest $request, RoomStandard $roomStandard): RoomStandardResource
    {
        $data = $request->standardData();

        if ((int) $data['consumable_id'] !== (int) $roomStandard->consumable_id) {
            $data += $this->addedItemState($roomStandard->room_id, $data['consumable_id']);
        }

        $roomStandard->update($data);

        return RoomStandardResource::make($this->prepare($roomStandard));
    }

    /**
     * Lepas barang/standar: hanya baris standar yang dihapus, transaksi ruangan tetap.
     */
    public function destroy(RoomStandard $roomStandard): JsonResponse
    {
        $name = $roomStandard->item_name;
        $roomStandard->delete();

        return response()->json(['message' => "Standar {$name} dihapus."]);
    }

    /**
     * Kartu ruangan: riwayat transaksi ruangan + barang baris ini (termasuk sebelum barang pernah dilepas), terbaru dulu.
     */
    public function transactions(Request $request, RoomStandard $roomStandard): AnonymousResourceCollection
    {
        $request->validate(['per_page' => ['nullable', 'integer', 'min:1', 'max:100']]);

        if ($roomStandard->count_method !== CountMethod::Manual) {
            throw ValidationException::withMessages(['standard' => 'Baris ini bukan barang stok.']);
        }

        return RoomStockTransactionResource::collection(
            RoomStockTransaction::query()
                ->where('room_id', $roomStandard->room_id)
                ->where('consumable_id', $roomStandard->consumable_id)
                ->with('creator')
                ->orderByDesc('id')
                ->paginate($request->integer('per_page', 20))
                ->withQueryString(),
        );
    }

    /**
     * Tandai sudah dicek. `items` dari klien lama diabaikan (tidak ditolak); Ada barang diubah lewat adjust().
     */
    public function check(Request $request, Room $room): JsonResponse
    {
        $room = $this->readiness->markChecked($room, $request->user());

        return $this->checklistResponse($room);
    }

    /**
     * Catat perubahan satu barang (popup): satu transaksi ruangan, atau Konfirmasi jumlah untuk baris Belum dicek.
     */
    public function adjust(AdjustRoomStockRequest $request, RoomStandard $roomStandard): JsonResponse
    {
        $result = $this->readiness->adjust(
            $roomStandard,
            (int) $request->validated('quantity'),
            $request->validated('reason'),
            $request->validated('note'),
            $request->user(),
        );

        $transaction = $result['transaction'];

        return response()->json([
            'message' => $transaction ? "{$transaction->type->value} dicatat." : 'Jumlah dikonfirmasi.',
            'data' => RoomStandardResource::make($this->prepare($result['standard'])),
            'transaction' => $transaction ? RoomStockTransactionResource::make($transaction->load('creator')) : null,
        ]);
    }

    public function fillFromStock(FillFromStockRequest $request, RoomStandard $roomStandard): JsonResponse
    {
        $result = $this->readiness->fillFromStock(
            $roomStandard,
            $request->user(),
            $request->validated('quantity'),
            $request->validated('note'),
        );

        return response()->json([
            'message' => $result['remaining_shortage'] > 0
                ? "Diisi {$result['filled']}, masih kurang {$result['remaining_shortage']}."
                : "Diisi {$result['filled']}, sudah sesuai standar.",
            'filled' => $result['filled'],
            'remaining_shortage' => $result['remaining_shortage'],
            'stock_remaining' => $result['stock_remaining'],
            'data' => RoomStandardResource::make($this->prepare($result['standard'])),
        ]);
    }

    private function checklistResponse(Room $room): JsonResponse
    {
        $standards = $this->readiness->checklist($room);

        return response()->json([
            'data' => [
                'assets' => $this->readiness->assetGroups($room, $standards),
                'consumables' => RoomStandardResource::collection(
                    $standards->filter(fn (RoomStandard $standard) => $standard->count_method === CountMethod::Manual)->values(),
                ),
                'stored_stock' => $this->readiness->storedStock($room),
            ],
            'meta' => [
                'room' => RoomResource::make($room->load(['building', 'lastCheckedBy'])),
                'last_checked_by' => $room->lastCheckedBy?->name,
                'summary' => $this->readiness->summarize($standards),
            ],
        ]);
    }

    /**
     * Barang yang ditambahkan ke ruangan (baru atau setelah dilepas): Ada = saldo terakhir ruangan + barang (0 tanpa riwayat),
     * selalu Belum dicek. Bukan perubahan saldo, sehingga tidak menulis transaksi.
     *
     * @return array{actual_quantity?: int, checked_at?: null}
     */
    private function addedItemState(int $roomId, ?int $consumableId): array
    {
        return $consumableId === null ? [] : ['actual_quantity' => $this->roomStock->balance($roomId, $consumableId), 'checked_at' => null];
    }

    private function prepare(RoomStandard $standard): RoomStandard
    {
        $standard->load(RoomReadinessService::RELATIONS);

        $this->readiness->fillLastUsage($this->readiness->fillQrCounts(new Collection([$standard])));

        return $standard;
    }
}
