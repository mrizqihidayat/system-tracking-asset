<?php

namespace App\Models;

use App\Enums\RequestStatus;
use App\Support\Search;
use Database\Factories\SupplyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable(['request_date', 'catalog_id', 'consumable_id', 'item_name', 'quantity', 'purpose', 'room_id', 'room_standard_id', 'applied_to', 'provider_department_id', 'request_method', 'lead_time_days', 'must_return', 'status', 'received_at', 'returned_at', 'note', 'created_by'])]
class SupplyRequest extends Model
{
    /** @use HasFactory<SupplyRequestFactory> */
    use HasFactory;

    /**
     * Saran awal cara permintaan; request_method teks bebas per permintaan (log jalur pengiriman).
     */
    public const DEFAULT_REQUEST_METHODS = ['Memo', 'Form', 'Email', 'WhatsApp', 'Lainnya'];

    /**
     * Tujuan penambahan saat Diterima (applied_to), dipakai untuk mengurangi kembali.
     */
    public const APPLIED_TO_STOCK = 'stok';

    public const APPLIED_TO_ROOM = 'ruangan';

    /**
     * Dampak perubahan status terakhir, diisi SupplyRequestService::changeStatus() (bukan kolom tabel).
     *
     * @var array{message: string, clamped: bool}|null
     */
    public ?array $statusImpact = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => RequestStatus::Diajukan->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'request_date' => 'date',
            'quantity' => 'integer',
            'lead_time_days' => 'integer',
            'must_return' => 'boolean',
            'status' => RequestStatus::class,
            'received_at' => 'datetime',
            'returned_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<RequestCatalog, $this>
     */
    public function catalog(): BelongsTo
    {
        return $this->belongsTo(RequestCatalog::class, 'catalog_id');
    }

    /**
     * @return BelongsTo<Consumable, $this>
     */
    public function consumable(): BelongsTo
    {
        return $this->belongsTo(Consumable::class);
    }

    /**
     * @return BelongsTo<RoomStandard, $this>
     */
    public function roomStandard(): BelongsTo
    {
        return $this->belongsTo(RoomStandard::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function providerDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'provider_department_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Batas respons = tanggal permintaan + snapshot lead time; null tanpa lead time (tanpa katalog).
     */
    public function responseDueDate(): ?Carbon
    {
        return $this->lead_time_days === null
            ? null
            : $this->request_date->copy()->addDays($this->lead_time_days);
    }

    /**
     * "Belum direspons" (dihitung, tidak disimpan): Diajukan yang melewati snapshot lead time.
     * Permintaan tanpa katalog tidak punya lead time, jadi tidak pernah dihitung.
     */
    public function isUnresponded(): bool
    {
        $due = $this->responseDueDate();

        return $this->status === RequestStatus::Diajukan && $due !== null && today()->greaterThan($due);
    }

    /**
     * Filter daftar permintaan (SPEC §9); dipakai daftar & laporan.
     *
     * @param  Builder<SupplyRequest>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['id'] ?? null, fn ($query, $id) => $query->whereKey($id))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('supply_requests.status', $status))
            ->when(filter_var($filters['unresponded'] ?? false, FILTER_VALIDATE_BOOLEAN), fn ($query) => $query->unresponded())
            ->when($filters['provider_department_id'] ?? null, fn ($query, $id) => $query->where('provider_department_id', $id))
            ->when($filters['room_id'] ?? null, fn ($query, $id) => $query->where('room_id', $id))
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('request_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('request_date', '<=', $to));

        Search::apply($query, $filters['search'] ?? null, ['supply_requests.item_name', 'supply_requests.purpose']);
    }

    /**
     * Terbaru dulu.
     *
     * @param  Builder<SupplyRequest>  $query
     */
    #[Scope]
    protected function listOrder(Builder $query): void
    {
        $query->orderByDesc('request_date')->orderByDesc('id');
    }

    /**
     * Rumus sama dengan isUnresponded() (dipakai filter daftar & dashboard T15).
     *
     * @param  Builder<SupplyRequest>  $query
     */
    #[Scope]
    protected function unresponded(Builder $query): void
    {
        $dueDate = DB::connection()->getDriverName() === 'sqlite'
            ? "date(supply_requests.request_date, '+' || supply_requests.lead_time_days || ' days')"
            : 'supply_requests.request_date + supply_requests.lead_time_days';

        $query->where('supply_requests.status', RequestStatus::Diajukan->value)
            ->whereNotNull('supply_requests.lead_time_days')
            ->whereRaw("{$dueDate} < ?", [today()->toDateString()]);
    }
}
