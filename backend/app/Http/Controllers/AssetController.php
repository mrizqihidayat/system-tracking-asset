<?php

namespace App\Http\Controllers;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Http\Requests\DeleteAssetRequest;
use App\Http\Requests\IndexAssetRequest;
use App\Http\Requests\MarkAssetFoundRequest;
use App\Http\Requests\MoveAssetLocationRequest;
use App\Http\Requests\ReportAssetRequest;
use App\Http\Requests\StoreAssetRequest;
use App\Http\Requests\StoreAssetServiceRequest;
use App\Http\Requests\UpdateAssetRequest;
use App\Http\Resources\AssetResource;
use App\Models\Asset;
use App\Models\AssetType;
use App\Services\AssetService;
use App\Services\CodeGenerator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AssetController extends Controller
{
    /**
     * Relasi untuk daftar & detail (lokasi asal + lokasi peminjaman saat ini).
     */
    private const RELATIONS = [
        'assetType.category',
        'sourceDepartment',
        'room.building',
        'activeLoan.destinationRoom.building',
        'latestLoan',
    ];

    public function __construct(private AssetService $assets) {}

    /**
     * Daftar aset + filter + paginasi (urut kode); aset Tidak Layak Pakai ikut tampil (filter status).
     * Pencarian: nama, kode, nomor seri, dan kode lama.
     */
    public function index(IndexAssetRequest $request): AnonymousResourceCollection
    {
        $filters = $request->validated();

        $query = Asset::query()->with(self::RELATIONS)->filter($filters);

        $assets = $query->orderBy('code')
            ->paginate($filters['per_page'] ?? IndexAssetRequest::DEFAULT_PER_PAGE)
            ->withQueryString();

        return AssetResource::collection($assets);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $asset = $this->assets->create($request->validated(), $request->user());

        return AssetResource::make($asset->load([...self::RELATIONS, 'codeHistories']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Asset $asset): AssetResource
    {
        return AssetResource::make($asset->load([...self::RELATIONS, 'codeHistories']));
    }

    /**
     * Dampak hapus aset untuk popup konfirmasi (jumlah pinjaman, pinjaman aktif, riwayat, file,
     * standar ruangan terpengaruh, dan apakah confirm_code wajib).
     */
    public function deleteImpact(Asset $asset): JsonResponse
    {
        return response()->json(['data' => $this->assets->deleteImpact($asset)]);
    }

    /**
     * Pratinjau kode jika jenis diganti (untuk peringatan di frontend); nomor tidak dipakai.
     */
    public function codePreview(Request $request, Asset $asset, CodeGenerator $codes): JsonResponse
    {
        $validated = $request->validate(['asset_type_id' => ['required', 'integer', 'exists:asset_types,id']]);
        $type = AssetType::query()->findOrFail($validated['asset_type_id']);
        $changes = $type->id !== $asset->asset_type_id;

        return response()->json(['data' => [
            'current_code' => $asset->code,
            'new_code' => $changes ? $codes->previewAssetCode($type) : $asset->code,
            'code_will_change' => $changes,
        ]]);
    }

    /**
     * Ubah data. Jika jenis diganti, meta.code_changed berisi kode lama & baru.
     */
    public function update(UpdateAssetRequest $request, Asset $asset): AssetResource
    {
        $result = $this->assets->update($asset, $request->validated(), $request->user());
        $asset = $result['asset']->load([...self::RELATIONS, 'codeHistories']);

        return AssetResource::make($asset)->additional(['meta' => [
            'code_changed' => $result['previous_code'] === null ? null : [
                'from' => $result['previous_code'],
                'to' => $asset->code,
            ],
        ]]);
    }

    /**
     * Catat kerusakan (type=rusak), kehilangan (type=hilang), atau tidak layak pakai (type=tidak_layak_pakai).
     */
    public function report(ReportAssetRequest $request, Asset $asset): AssetResource
    {
        $data = $request->validated();

        $party = $data['party'] ?? null;

        $asset = match ($data['type']) {
            ReportAssetRequest::TYPE_DAMAGE => $this->assets->reportDamage(
                $asset,
                AssetCondition::from($data['condition']),
                AssetStatus::tryFrom((string) ($data['status'] ?? '')),
                $data['note'],
                $party,
                $request->user(),
            ),
            ReportAssetRequest::TYPE_LOST => $this->assets->reportLost($asset, $data['note'], $party, $request->user()),
            ReportAssetRequest::TYPE_UNFIT => $this->assets->reportUnfit($asset, $data['note'], $party, $request->user()),
        };

        return AssetResource::make($asset->load(self::RELATIONS));
    }

    /**
     * Tandai ditemukan (khusus aset Hilang).
     */
    public function markFound(MarkAssetFoundRequest $request, Asset $asset): AssetResource
    {
        $data = $request->validated();

        $asset = $this->assets->markFound(
            $asset,
            AssetCondition::from($data['condition']),
            AssetStatus::tryFrom((string) ($data['status'] ?? '')),
            $data['note'] ?? null,
            $data['party'] ?? null,
            $request->user(),
        );

        return AssetResource::make($asset->load(self::RELATIONS));
    }

    /**
     * Pindah lokasi asal (scan / aksi cepat); riwayat Pindah Lokasi.
     */
    public function moveLocation(MoveAssetLocationRequest $request, Asset $asset): AssetResource
    {
        $asset = $this->assets->moveLocation(
            $asset,
            (int) $request->validated('room_id'),
            $request->validated('note'),
            $request->user(),
        );

        return AssetResource::make($asset->load(self::RELATIONS));
    }

    public function storeService(StoreAssetServiceRequest $request, Asset $asset): AssetResource
    {
        $asset = $this->assets->recordService($asset, $request->validated(), $request->user());

        return AssetResource::make($asset->load(self::RELATIONS));
    }

    /**
     * Hapus aset PERMANEN (semua status). confirm_code wajib jika aset punya riwayat pinjam.
     */
    public function destroy(DeleteAssetRequest $request, Asset $asset): JsonResponse
    {
        $code = $asset->code;
        $this->assets->delete($asset, $request->validated('confirm_code'));

        return response()->json(['message' => "Aset {$code} dihapus."]);
    }
}
