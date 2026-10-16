<?php

namespace App\Models;

use App\Enums\RequestStatus;
use Database\Factories\SupplyRequestFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

#[Fillable(['request_date', 'catalog_id', 'item_name', 'quantity', 'purpose', 'room_id', 'provider_department_id', 'status', 'received_at', 'returned_at', 'note', 'created_by'])]
class SupplyRequest extends Model
{
    /** @use HasFactory<SupplyRequestFactory> */
    use HasFactory;

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
     * Batas respons = tanggal permintaan + lead time katalog; null tanpa katalog.
     */
    public function responseDueDate(): ?Carbon
    {
        return $this->catalog === null
            ? null
            : $this->request_date->copy()->addDays($this->catalog->lead_time_days);
    }

    /**
     * "Belum direspons" (dihitung, tidak disimpan): Diajukan yang melewati lead time katalog.
     * Permintaan tanpa katalog tidak punya lead time, jadi tidak pernah dihitung.
     */
    public function isUnresponded(): bool
    {
        $due = $this->responseDueDate();

        return $this->status === RequestStatus::Diajukan && $due !== null && today()->greaterThan($due);
    }

    /**
     * Rumus sama dengan isUnresponded() (dipakai filter daftar & dashboard T15).
     *
     * @param  Builder<SupplyRequest>  $query
     */
    #[Scope]
    protected function unresponded(Builder $query): void
    {
        $query->where('supply_requests.status', RequestStatus::Diajukan->value)
            ->whereHas('catalog', function ($catalog) {
                $dueDate = DB::connection()->getDriverName() === 'sqlite'
                    ? "date(supply_requests.request_date, '+' || request_catalog.lead_time_days || ' days')"
                    : 'supply_requests.request_date + request_catalog.lead_time_days';

                $catalog->whereRaw("{$dueDate} < ?", [today()->toDateString()]);
            });
    }
}
