<?php

namespace App\Services;

use App\Enums\ConsumableStatus;
use App\Enums\RequestStatus;
use App\Enums\StockTransactionType;
use App\Models\Consumable;
use App\Models\RequestCatalog;
use App\Models\SupplyRequest;
use App\Models\User;
use App\Support\AppCache;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Permintaan ke departemen lain (SPEC §9): catat, ubah status (transisi terkunci, ikut mengubah stok),
 * saran permintaan, hapus katalog.
 */
class SupplyRequestService
{
    public function __construct(private ConsumableService $consumables) {}

    /**
     * Item & departemen penyedia diisi dari katalog bila dikirim; tanggal default hari ini.
     *
     * @param  array<string, mixed>  $data
     */
    public function create(array $data, User $user): SupplyRequest
    {
        return SupplyRequest::create($this->resolve($data) + ['created_by' => $user->id]);
    }

    /**
     * Satu permintaan per barang stok, semua ke departemen penyedia yang sama, dalam satu transaksi:
     * gagal satu = tidak ada yang tersimpan. Barang harus bersumber (minta ke) departemen itu.
     *
     * @param  array{provider_department_id: int, request_method: string, request_date?: ?string, purpose?: ?string, room_id?: ?int, note?: ?string, items: list<array{consumable_id: int, quantity: int}>}  $data
     * @return Collection<int, SupplyRequest>
     */
    public function createBatch(array $data, User $user): Collection
    {
        $consumables = Consumable::query()->whereKey(array_column($data['items'], 'consumable_id'))->get()->keyBy('id');

        foreach ($data['items'] as $index => $item) {
            if ((int) $consumables[$item['consumable_id']]->provider_department_id !== (int) $data['provider_department_id']) {
                throw ValidationException::withMessages([
                    "items.{$index}.consumable_id" => "{$consumables[$item['consumable_id']]->name} tidak diminta ke departemen penyedia ini.",
                ]);
            }
        }

        $shared = collect($data)->only(['provider_department_id', 'request_method', 'request_date', 'purpose', 'room_id', 'note'])->all();

        return DB::transaction(fn () => collect($data['items'])->map(fn (array $item) => $this->create(
            $shared + ['consumable_id' => $item['consumable_id'], 'quantity' => $item['quantity'], 'must_return' => false],
            $user,
        )));
    }

    /**
     * Hanya yang masih Diajukan yang boleh diedit.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(SupplyRequest $request, array $data): SupplyRequest
    {
        return DB::transaction(function () use ($request, $data) {
            $request = $this->lock($request);

            if ($request->status !== RequestStatus::Diajukan) {
                throw ValidationException::withMessages(['status' => 'Hanya permintaan berstatus Diajukan yang dapat diubah.']);
            }

            $request->update($this->resolve($data));

            return $request;
        });
    }

    /**
     * Terbuka: Diajukan (termasuk "Belum direspons") → Diterima | Dibatalkan.
     * Selesai: Diterima → Dikembalikan (hanya jika snapshot "perlu dikembalikan") | Dibatalkan;
     * Diterima sebagian → Dikembalikan saja (hanya jika "perlu dikembalikan"), selain itu final: sisa yang belum datang
     * tidak bisa diterima lagi dan diajukan ulang lewat saran permintaan.
     * Barang stok: Diterima dengan jumlah yang datang (bawaan: semua sisa); kurang dari sisa → Diterima sebagian (final).
     */
    public function changeStatus(SupplyRequest $request, RequestStatus $to, ?string $note, User $user, ?int $received = null): SupplyRequest
    {
        return DB::transaction(function () use ($request, $to, $note, $user, $received) {
            $request = $this->lock($request);
            $this->ensureTransition($request, $to);

            $effect = $this->stockEffect($request, $to, $received, lock: true);
            $this->applyEffect($request, $to, $effect, $user);

            $request->status = $effect['status'] ?? $to;
            $request->statusImpact = $this->impact($request, $effect);

            match ($to) {
                RequestStatus::Diterima => $request->received_at = now(),
                RequestStatus::Dikembalikan => $request->returned_at = now(),
                default => null,
            };

            if ($note !== null && $note !== '') {
                $request->note = $note;
            }

            $request->save();

            return $request;
        });
    }

    /**
     * Pratinjau dampak perubahan status ke stok (popup sebelum menyimpan).
     *
     * @return array{message: string, clamped: bool}
     */
    public function statusImpact(SupplyRequest $request, RequestStatus $to, ?int $received = null): array
    {
        $this->ensureTransition($request, $to);

        return $this->impact($request, $this->stockEffect($request, $to, $received));
    }

    private function ensureTransition(SupplyRequest $request, RequestStatus $to): void
    {
        $allowed = match ($request->status) {
            RequestStatus::Diajukan => [RequestStatus::Diterima, RequestStatus::Dibatalkan],
            RequestStatus::Diterima => [RequestStatus::Dikembalikan, RequestStatus::Dibatalkan],
            RequestStatus::DiterimaSebagian => [RequestStatus::Dikembalikan],
            default => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Status {$request->status->value} tidak dapat diubah ke {$to->value}."]);
        }

        if ($to === RequestStatus::Dikembalikan && ! $request->must_return) {
            throw ValidationException::withMessages(['status' => 'Barang ini tidak perlu dikembalikan.']);
        }
    }

    /**
     * Sasaran & jumlah perubahan stok. null = tidak mengubah stok.
     * Dikembalikan/Dibatalkan setelah Diterima atau Diterima sebagian: kurangi jumlah yang benar-benar sudah masuk
     * (bukan jumlah yang diajukan), tidak sampai di bawah 0.
     *
     * @return array{target: Consumable, adding: bool, amount: int, current: int, clamped: bool, status?: RequestStatus}|null
     */
    private function stockEffect(SupplyRequest $request, RequestStatus $to, ?int $received, bool $lock = false): ?array
    {
        $target = $this->consumable($request->consumable_id, $lock);

        if (! $target) {
            return null;
        }

        if ($to === RequestStatus::Diterima) {
            $remaining = $request->quantity - $request->received_quantity;
            $amount = $received ?? $remaining;

            if ($amount > $remaining) {
                throw ValidationException::withMessages(['received_quantity' => "Jumlah diterima melebihi sisa permintaan ({$remaining})."]);
            }

            return [
                'target' => $target, 'adding' => true, 'amount' => $amount, 'current' => $target->quantity, 'clamped' => false,
                'status' => $amount < $remaining ? RequestStatus::DiterimaSebagian : RequestStatus::Diterima,
            ];
        }

        if (! in_array($request->status, [RequestStatus::Diterima, RequestStatus::DiterimaSebagian], true) || $request->received_quantity === 0) {
            return null;
        }

        return [
            'target' => $target, 'adding' => false, 'amount' => min($request->received_quantity, $target->quantity),
            'current' => $target->quantity, 'clamped' => $target->quantity < $request->received_quantity,
        ];
    }

    private function consumable(?int $id, bool $lock): ?Consumable
    {
        if (! $id) {
            return null;
        }

        return ($lock ? Consumable::query()->lockForUpdate() : Consumable::query())->find($id);
    }

    /**
     * Barang datang → Barang Masuk; dikembalikan/dibatalkan setelah Diterima → Koreksi dengan alasan otomatis.
     *
     * @param  array{target: Consumable, adding: bool, amount: int, current: int, clamped: bool, status?: RequestStatus}|null  $effect
     */
    private function applyEffect(SupplyRequest $request, RequestStatus $to, ?array $effect, User $user): void
    {
        if ($effect === null) {
            return;
        }

        if ($effect['adding']) {
            $this->consumables->record($effect['target'], StockTransactionType::BarangMasuk, $effect['amount'], [
                'note' => "Dari permintaan #{$request->id}",
                'supply_request_id' => $request->id,
            ], $user);
            $request->received_quantity += $effect['amount'];

            return;
        }

        if ($effect['amount'] > 0) {
            $this->consumables->record($effect['target'], StockTransactionType::Koreksi, -$effect['amount'], [
                'note' => "Permintaan #{$request->id} {$to->value}",
                'supply_request_id' => $request->id,
            ], $user);
        }

        if ($to === RequestStatus::Dibatalkan) {
            $request->received_quantity = 0;
        }
    }

    /**
     * @param  array{target: Consumable, adding: bool, amount: int, current: int, clamped: bool, status?: RequestStatus}|null  $effect
     * @return array{message: string, clamped: bool}
     */
    private function impact(SupplyRequest $request, ?array $effect): array
    {
        if ($effect === null) {
            return ['message' => 'Tidak mengubah stok.', 'clamped' => false];
        }

        $subject = "Stok {$effect['target']->name}";

        if ($effect['adding']) {
            return ['message' => "{$subject} akan bertambah {$effect['amount']}.", 'clamped' => false];
        }

        if ($effect['clamped']) {
            return [
                'message' => $effect['current'] === 0
                    ? "{$subject} sudah 0, tidak ada yang dikurangi. Cek manual selisihnya."
                    : "{$subject} hanya tersisa {$effect['current']}, jadi berkurang {$effect['amount']} sampai 0. Cek manual selisihnya.",
                'clamped' => true,
            ];
        }

        return ['message' => "{$subject} akan berkurang {$effect['amount']}.", 'clamped' => false];
    }

    /**
     * Pemakaian item katalog (popup sebelum hapus).
     *
     * @return array{supply_requests: int, total: int}
     */
    public function catalogUsage(RequestCatalog $catalog): array
    {
        $requests = $catalog->supplyRequests()->count();

        return ['supply_requests' => $requests, 'total' => $requests];
    }

    /**
     * Hapus item katalog walau dipakai: tautan catalog_id di permintaan dilepas
     * dalam satu transaksi. Riwayat permintaan tetap utuh (item, departemen, cara, lead time, must_return tersimpan).
     */
    public function deleteCatalog(RequestCatalog $catalog): void
    {
        DB::transaction(function () use ($catalog) {
            $catalog = RequestCatalog::query()->whereKey($catalog->getKey())->lockForUpdate()->firstOrFail();

            $catalog->supplyRequests()->update(['catalog_id' => null]);

            // Observer katalog (deleted) mengosongkan cache master & ops yang memuat tautan tadi.
            $catalog->delete();
        });
    }

    /**
     * Saran permintaan per departemen penyedia: hanya dari stok Menipis/Habis.
     * has_open_request = sudah ada permintaan terbuka (Diajukan, termasuk Belum direspons) untuk barang itu.
     * Diterima sebagian sudah selesai: barang yang stoknya masih kurang muncul lagi agar bisa diminta ulang.
     *
     * @return list<array{provider_department: array{id: int, name: string}, items: list<array<string, mixed>>}>
     */
    public function suggestions(): array
    {
        return AppCache::remember(AppCache::GROUP_OPS, 'supply-suggestions', fn () => $this->buildSuggestions());
    }

    /**
     * @return list<array{provider_department: array{id: int, name: string}, items: list<array<string, mixed>>}>
     */
    private function buildSuggestions(): array
    {
        $open = SupplyRequest::query()->where('status', RequestStatus::Diajukan->value)->get();

        $items = $this->stockSuggestions()
            ->map(function (array $item) use ($open) {
                $item['has_open_request'] = $open->contains(fn (SupplyRequest $request) => $this->matches($request, $item));

                return $item;
            });

        return $items
            ->groupBy(fn (array $item) => $item['provider_department']['id'])
            ->map(fn (Collection $group) => [
                'provider_department' => $group->first()['provider_department'],
                'items' => $group->values()->map(fn (array $item) => collect($item)->except('provider_department')->all())->all(),
            ])
            ->sortBy(fn (array $group) => mb_strtolower($group['provider_department']['name']))
            ->values()
            ->all();
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function stockSuggestions(): Collection
    {
        return Consumable::query()
            ->where(fn ($query) => $query->withStatus(ConsumableStatus::Habis)->orWhere(fn ($q) => $q->withStatus(ConsumableStatus::Menipis)))
            ->with('providerDepartment')
            ->orderBy('name')
            ->get()
            ->map(fn (Consumable $consumable) => [
                'item_name' => $consumable->name,
                'quantity' => max($consumable->min_quantity - $consumable->quantity, 1),
                'consumable_id' => $consumable->id,
                'stock_status' => $consumable->status()->value,
                'provider_department' => $this->department($consumable->providerDepartment),
            ]);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function matches(SupplyRequest $request, array $item): bool
    {
        if ((int) $request->provider_department_id !== (int) $item['provider_department']['id']) {
            return false;
        }

        // Permintaan tertaut stok dicocokkan per barang.
        if ($request->consumable_id !== null) {
            return (int) $request->consumable_id === (int) $item['consumable_id'];
        }

        return mb_strtolower($request->item_name) === mb_strtolower($item['item_name']);
    }

    /**
     * @return array{id: int, name: string}
     */
    private function department(object $department): array
    {
        return ['id' => $department->id, 'name' => $department->name];
    }

    /**
     * Snapshot lead time dari katalog & "perlu dikembalikan" dari pilihan form (default katalog); riwayat tidak
     * berubah saat katalog diedit/dihapus.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolve(array $data): array
    {
        $catalog = empty($data['catalog_id']) ? null : RequestCatalog::query()->findOrFail($data['catalog_id']);

        if ($catalog) {
            $data['item_name'] = ($data['item_name'] ?? null) ?: $catalog->item_name;
            $data['provider_department_id'] = $catalog->provider_department_id;
        }

        // Permintaan tertaut stok memakai nama barang.
        if (! empty($data['consumable_id'])) {
            $data['item_name'] = Consumable::query()->whereKey($data['consumable_id'])->value('name');
        }

        $data['lead_time_days'] = $catalog?->lead_time_days;
        // Pilihan di form yang disimpan; jika tidak dikirim mengikuti katalog (tanpa katalog: tidak dikembalikan).
        $data['must_return'] = (bool) ($data['must_return'] ?? $catalog?->must_return ?? false);

        $data['request_date'] = ($data['request_date'] ?? null) ?: today()->toDateString();

        return $data;
    }

    private function lock(SupplyRequest $request): SupplyRequest
    {
        return SupplyRequest::query()->with('catalog')->whereKey($request->getKey())->lockForUpdate()->firstOrFail();
    }
}
