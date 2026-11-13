<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\CountMethod;
use App\Enums\EventType;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use App\Models\AssetEvent;
use App\Models\AssetFile;
use App\Models\AssetType;
use App\Models\Department;
use App\Models\Loan;
use App\Models\RoomStandard;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Tambah, ubah, catat status, dan hapus aset (permanen). Semua perubahan di DB::transaction() + lockForUpdate()
 * dan dicatat ke riwayat lewat AssetEventService.
 */
class AssetService
{
    private const PARTY = 'SHD';

    /**
     * Label field untuk catatan riwayat "Ubah Data". Teks panjang cukup ditulis "diubah".
     */
    private const FIELD_LABELS = [
        'name' => 'Nama',
        'brand' => 'Merek',
        'serial_number' => 'Nomor seri',
        'specification' => 'Spesifikasi',
        'source_department_id' => 'Sumber',
        'pic_name' => 'PIC',
        'finance_code' => 'Kode aset keuangan',
        'acquisition_date' => 'Tanggal perolehan',
        'next_service_date' => 'Servis berikutnya',
        'note' => 'Catatan',
    ];

    private const LONG_TEXT_FIELDS = ['specification', 'note'];

    public function __construct(
        private CodeGenerator $codes,
        private AssetEventService $events,
        private AssetFileService $files,
    ) {}

    /**
     * Aset baru: kode otomatis, status dari kondisi (SPEC §5), riwayat Registrasi.
     *
     * @param  array<string, mixed>  $data  data tervalidasi (condition & status opsional)
     */
    public function create(array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($data, $actor) {
            $type = AssetType::query()->findOrFail($data['asset_type_id']);
            $condition = AssetCondition::from($data['condition'] ?? AssetCondition::Baik->value);
            $status = $condition->resolveStatus(AssetStatus::tryFrom((string) ($data['status'] ?? '')));

            $asset = Asset::create([
                ...Arr::except($data, ['condition', 'status']),
                'code' => $this->codes->nextAssetCode($type),
                'condition' => $condition,
                'status' => $status,
                'created_by' => $actor->id,
            ]);

            $this->events->record($asset, EventType::Registrasi, $actor, [
                'party' => self::PARTY,
                'condition' => $condition,
                'to_room_id' => $asset->room_id,
            ]);

            return $asset;
        });
    }

    /**
     * Ubah data aset (status & kondisi tidak lewat sini).
     *
     * - Jenis berubah → kode baru dari nomor berikutnya jenis baru; kode lama disimpan di
     *   asset_code_histories dan dicatat di riwayat "Ubah Data".
     * - Ruangan berubah → riwayat "Pindah Lokasi".
     * - Field lain berubah → riwayat "Ubah Data" berisi daftar perubahan.
     *
     * @param  array<string, mixed>  $data
     * @return array{asset: Asset, previous_code: ?string}
     */
    public function update(Asset $asset, array $data, User $actor): array
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $asset = Asset::query()->with('assetType')->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
            $changes = [];
            $previousCode = null;

            if (isset($data['asset_type_id']) && (int) $data['asset_type_id'] !== $asset->asset_type_id) {
                [$previousCode, $changes] = $this->changeType($asset, (int) $data['asset_type_id'], $actor);
            }

            $asset->fill(Arr::except($data, ['asset_type_id']));

            foreach (self::FIELD_LABELS as $field => $label) {
                if ($asset->isDirty($field)) {
                    $changes[] = $this->describeChange($asset, $field, $label);
                }
            }

            $fromRoomId = $asset->getOriginal('room_id');
            $roomChanged = $asset->isDirty('room_id');

            $asset->save();

            if ($changes !== []) {
                $this->events->record($asset, EventType::UbahData, $actor, [
                    'party' => self::PARTY,
                    'note' => implode('; ', $changes),
                ]);
            }

            if ($roomChanged) {
                $this->recordMove($asset, $fromRoomId, $actor);
            }

            return ['asset' => $asset, 'previous_code' => $previousCode];
        });
    }

    /**
     * Pindah lokasi asal (scan QR / aksi cepat). Berlaku untuk semua status; ruangan sama ditolak.
     */
    public function moveLocation(Asset $asset, int $roomId, ?string $note, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $roomId, $note, $actor) {
            $asset = $this->lock($asset);

            if ($asset->room_id === $roomId) {
                throw ValidationException::withMessages(['room_id' => 'Ruangan sama dengan lokasi saat ini.']);
            }

            $fromRoomId = $asset->room_id;
            $asset->update(['room_id' => $roomId]);
            $this->recordMove($asset, $fromRoomId, $actor, $note);

            return $asset;
        });
    }

    /**
     * Dampak hapus aset (untuk popup konfirmasi frontend).
     *
     * @return array{loans_count: int, active_loan: array{borrower_name: string, borrower_unit: string}|null, events_count: int, files_count: int, requires_confirm_code: bool, affected_room_standards: list<array{room_id: int, room: string, building: string, item_name: string}>}
     */
    public function deleteImpact(Asset $asset): array
    {
        $loansCount = $asset->loans()->count();
        $activeLoan = $asset->activeLoan()->first();

        $standards = RoomStandard::query()
            ->with('room.building')
            ->where('room_id', $asset->room_id)
            ->where('count_method', CountMethod::Qr->value)
            ->where('asset_type_id', $asset->asset_type_id)
            ->get()
            ->map(fn (RoomStandard $standard) => [
                'room_id' => $standard->room_id,
                'room' => $standard->room->name,
                'building' => $standard->room->building->name,
                'item_name' => $standard->item_name,
            ])
            ->values()
            ->all();

        return [
            'loans_count' => $loansCount,
            'active_loan' => $activeLoan ? [
                'borrower_name' => $activeLoan->borrower_name,
                'borrower_unit' => $activeLoan->borrower_unit,
            ] : null,
            'events_count' => $asset->events()->count(),
            'files_count' => $asset->files()->count(),
            'requires_confirm_code' => $loansCount > 0,
            'affected_room_standards' => $standards,
        ];
    }

    /**
     * Hapus aset PERMANEN (semua status, termasuk Dipinjam). Satu transaksi dengan lock: kode lama,
     * file, riwayat, dan pinjaman (termasuk yang aktif) dihapus lebih dulu (FK tetap RESTRICT).
     * Aset yang punya riwayat pinjam wajib confirm_code = kode aset. File storage dihapus setelah commit.
     * Riwayat ikut terhapus, jadi siapa yang menghapus dicatat di log keamanan setelah commit.
     */
    public function delete(Asset $asset, ?string $confirmCode, User $actor): void
    {
        DB::transaction(function () use ($asset, $confirmCode, $actor) {
            $asset = $this->lock($asset);

            if ($asset->loans()->exists() && mb_strtoupper(trim((string) $confirmCode)) !== mb_strtoupper($asset->code)) {
                throw ValidationException::withMessages(['confirm_code' => 'Kode konfirmasi tidak cocok.']);
            }

            $paths = $asset->files()->pluck('path')->all();

            AssetCodeHistory::query()->where('asset_id', $asset->id)->delete();
            AssetFile::query()->where('asset_id', $asset->id)->delete();
            AssetEvent::query()->where('asset_id', $asset->id)->delete();
            Loan::query()->where('asset_id', $asset->id)->delete();
            $asset->delete();

            $this->files->deleteFromStorageAfterCommit($paths);

            DB::afterCommit(fn () => Log::channel('security')->info('asset.deleted', [
                'asset_code' => $asset->code,
                'user_id' => $actor->id,
                'username' => $actor->loginId(),
            ]));
        });
    }

    /**
     * Catat tidak layak pakai: hanya dari Tersedia/Perbaikan; tidak ada aksi untuk mengembalikannya.
     */
    public function reportUnfit(Asset $asset, string $note, ?string $party, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $note, $party, $actor) {
            $asset = $this->lock($asset);

            if ($asset->status === AssetStatus::Dipinjam) {
                throw ValidationException::withMessages(['asset' => 'Aset sedang dipinjam. Selesaikan lewat peminjaman.']);
            }

            if (! in_array($asset->status, [AssetStatus::Tersedia, AssetStatus::Perbaikan], true)) {
                throw ValidationException::withMessages(['asset' => "Aset berstatus {$asset->status->value}, tidak bisa dicatat tidak layak pakai."]);
            }

            $asset->update(['status' => AssetStatus::TidakLayakPakai]);

            $this->events->record($asset, EventType::TidakLayakPakai, $actor, [
                'party' => $party ?: self::PARTY,
                'condition' => $asset->condition,
                'note' => $note,
            ]);

            return $asset;
        });
    }

    /**
     * Catat kerusakan: kondisi diperbarui, status aturan A6. Jika aset sedang Dipinjam hanya kondisi
     * yang berubah (status final ditentukan saat terima kembali). Aset Hilang ditolak.
     */
    public function reportDamage(Asset $asset, AssetCondition $condition, ?AssetStatus $chosenStatus, string $note, ?string $party, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $condition, $chosenStatus, $note, $party, $actor) {
            $asset = $this->lock($asset);
            $this->ensureUsable($asset);

            $asset->condition = $condition;

            if ($asset->status !== AssetStatus::Dipinjam) {
                $asset->status = $condition->resolveStatus($chosenStatus);
            }

            $asset->save();

            $this->events->record($asset, EventType::LaporRusak, $actor, [
                'party' => $party ?: self::PARTY,
                'condition' => $condition,
                'note' => $note,
            ]);

            return $asset;
        });
    }

    /**
     * Catat kehilangan: status Hilang. Aset Dipinjam ditolak (diselesaikan lewat terima kembali, T11).
     */
    public function reportLost(Asset $asset, string $note, ?string $party, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $note, $party, $actor) {
            $asset = $this->lock($asset);

            if ($asset->status === AssetStatus::Dipinjam) {
                throw ValidationException::withMessages(['asset' => 'Aset sedang dipinjam. Selesaikan lewat peminjaman.']);
            }

            if ($asset->status === AssetStatus::Hilang) {
                throw ValidationException::withMessages(['asset' => 'Aset sudah berstatus Hilang.']);
            }

            if ($asset->status === AssetStatus::TidakLayakPakai) {
                throw ValidationException::withMessages(['asset' => 'Aset berstatus Tidak Layak Pakai.']);
            }

            $asset->update(['status' => AssetStatus::Hilang]);

            $this->events->record($asset, EventType::LaporHilang, $actor, [
                'party' => $party ?: self::PARTY,
                'note' => $note,
            ]);

            return $asset;
        });
    }

    /**
     * Tandai ditemukan (khusus aset Hilang): kondisi dipilih staf, status aturan A6 (tidak pernah Dipinjam).
     */
    public function markFound(Asset $asset, AssetCondition $condition, ?AssetStatus $chosenStatus, ?string $note, ?string $party, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $condition, $chosenStatus, $note, $party, $actor) {
            $asset = $this->lock($asset);

            if ($asset->status !== AssetStatus::Hilang) {
                throw ValidationException::withMessages(['asset' => 'Hanya aset berstatus Hilang yang bisa ditandai ditemukan.']);
            }

            $asset->update(['condition' => $condition, 'status' => $condition->resolveStatus($chosenStatus)]);

            $this->events->record($asset, EventType::Ditemukan, $actor, [
                'party' => $party ?: self::PARTY,
                'condition' => $condition,
                'note' => $note,
            ]);

            return $asset;
        });
    }

    /**
     * Catat servis: kondisi setelah servis, status aturan A6, servis berikutnya diperbarui jika diisi.
     * Aset Dipinjam atau Hilang ditolak.
     *
     * @param  array{service_date: string, vendor: string, condition: string, status?: ?string, next_service_date?: ?string, note?: ?string}  $data
     */
    public function recordService(Asset $asset, array $data, User $actor): Asset
    {
        return DB::transaction(function () use ($asset, $data, $actor) {
            $asset = $this->lock($asset);
            $this->ensureUsable($asset);

            if ($asset->status === AssetStatus::Dipinjam) {
                throw ValidationException::withMessages(['asset' => 'Aset sedang dipinjam.']);
            }

            $condition = AssetCondition::from($data['condition']);
            $asset->condition = $condition;
            $asset->status = $condition->resolveStatus(AssetStatus::tryFrom((string) ($data['status'] ?? '')));

            if (! empty($data['next_service_date'])) {
                $asset->next_service_date = $data['next_service_date'];
            }

            $asset->save();

            $this->events->record($asset, EventType::Servis, $actor, [
                'event_date' => Carbon::parse($data['service_date'])->startOfDay(),
                'party' => $data['vendor'],
                'condition' => $condition,
                'note' => $data['note'] ?? null,
            ]);

            return $asset;
        });
    }

    private function recordMove(Asset $asset, int $fromRoomId, User $actor, ?string $note = null): void
    {
        $this->events->record($asset, EventType::PindahLokasi, $actor, [
            'party' => self::PARTY,
            'from_room_id' => $fromRoomId,
            'to_room_id' => $asset->room_id,
            'note' => $note,
        ]);
    }

    private function lock(Asset $asset): Asset
    {
        return Asset::query()->whereKey($asset->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Aset Hilang atau Tidak Layak Pakai tidak bisa dicatat kerusakan atau diservis.
     */
    private function ensureUsable(Asset $asset): void
    {
        if ($asset->status === AssetStatus::Hilang) {
            throw ValidationException::withMessages(['asset' => 'Aset berstatus Hilang. Tandai ditemukan dulu.']);
        }

        if ($asset->status === AssetStatus::TidakLayakPakai) {
            throw ValidationException::withMessages(['asset' => 'Aset berstatus Tidak Layak Pakai.']);
        }
    }

    /**
     * @return array{0: string, 1: list<string>} kode lama dan catatan perubahan
     */
    private function changeType(Asset $asset, int $newTypeId, User $actor): array
    {
        $oldType = $asset->assetType;
        $newType = AssetType::query()->findOrFail($newTypeId);
        $previousCode = $asset->code;
        $newCode = $this->codes->nextAssetCode($newType);

        AssetCodeHistory::create([
            'asset_id' => $asset->id,
            'asset_type_id' => $oldType->id,
            'code' => $previousCode,
            'replaced_at' => now(),
            'replaced_by' => $actor->id,
        ]);

        $asset->forceFill(['asset_type_id' => $newType->id, 'code' => $newCode]);
        $asset->setRelation('assetType', $newType);

        return [$previousCode, [
            "Jenis: {$oldType->name} → {$newType->name}",
            "Kode: {$previousCode} → {$newCode}",
        ]];
    }

    private function describeChange(Asset $asset, string $field, string $label): string
    {
        if (in_array($field, self::LONG_TEXT_FIELDS, true)) {
            return "{$label} diubah";
        }

        $old = $asset->getOriginal($field);
        $new = $asset->getAttribute($field);

        if ($field === 'source_department_id') {
            $names = Department::query()->whereKey([$old, $new])->pluck('name', 'id');
            [$old, $new] = [$names[$old] ?? null, $names[$new] ?? null];
        }

        return "{$label}: {$this->formatValue($old)} → {$this->formatValue($new)}";
    }

    private function formatValue(mixed $value): string
    {
        return match (true) {
            $value === null || $value === '' => '-',
            $value instanceof CarbonInterface => $value->toDateString(),
            default => (string) $value,
        };
    }
}
