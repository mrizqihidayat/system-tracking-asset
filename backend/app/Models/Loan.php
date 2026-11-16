<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\FileType;
use App\Enums\LoanStatus;
use App\Support\Search;
use Carbon\CarbonInterface;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['asset_id', 'borrower_name', 'borrower_unit', 'purpose', 'destination_room_id', 'destination_note', 'borrow_date', 'handover_date', 'due_date', 'condition_out', 'approval_number', 'approval_date', 'borrowed_by', 'status', 'return_date', 'return_condition', 'return_room_id', 'received_by', 'cancel_reason', 'note'])]
class Loan extends Model
{
    /** @use HasFactory<LoanFactory> */
    use HasFactory;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => LoanStatus::Dipinjam->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'borrow_date' => 'date',
            'handover_date' => 'date',
            'due_date' => 'date',
            'approval_date' => 'date',
            'return_date' => 'datetime',
            'condition_out' => AssetCondition::class,
            'return_condition' => AssetCondition::class,
            'status' => LoanStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Asset, $this>
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Foto kondisi saat Terima kembali (T36): satu per peminjaman, tidak menggantikan foto peminjaman lain.
     *
     * @return HasOne<AssetFile, $this>
     */
    public function conditionPhoto(): HasOne
    {
        return $this->hasOne(AssetFile::class)->where('file_type', FileType::FotoKondisi->value);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function destinationRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'destination_room_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function returnRoom(): BelongsTo
    {
        return $this->belongsTo(Room::class, 'return_room_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function borrowedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'borrowed_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function receivedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasMany<AssetEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    /**
     * Terlambat = Dipinjam dan rencana kembali < hari ini (SPEC §7).
     */
    public function isOverdue(): bool
    {
        return $this->status === LoanStatus::Dipinjam && $this->due_date->lt(today());
    }

    /**
     * Belum diserahkan = Direncanakan dan tanggal rencana sudah lewat (tetap Direncanakan, T31).
     */
    public function isUnhanded(): bool
    {
        return $this->status === LoanStatus::Direncanakan && $this->borrow_date->lt(today());
    }

    /**
     * Periode pemakaian aset untuk cek bentrok (tanggal, inklusif). Dipinjam mulai tanggal penyerahan
     * (jika ada) dan, bila terlambat, dianggap berlanjut sampai hari ini karena barang belum kembali.
     *
     * @return array{0: CarbonInterface, 1: CarbonInterface}
     */
    public function occupiedPeriod(): array
    {
        if ($this->status === LoanStatus::Dipinjam) {
            return [$this->handover_date ?? $this->borrow_date, $this->due_date->max(today())];
        }

        return [$this->borrow_date, $this->due_date];
    }

    /**
     * Jatuh tempo hari ini = Dipinjam dan rencana kembali = hari ini.
     */
    public function isDueToday(): bool
    {
        return $this->status === LoanStatus::Dipinjam && $this->due_date->isSameDay(today());
    }

    /**
     * Filter daftar peminjaman (SPEC §7); dipakai daftar & laporan.
     *
     * @param  Builder<Loan>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when(self::flag($filters, 'overdue'), fn ($query) => $query->overdue())
            ->when(self::flag($filters, 'due_today'), fn ($query) => $query->dueToday())
            ->when($filters['from'] ?? null, fn ($query, $from) => $query->whereDate('borrow_date', '>=', $from))
            ->when($filters['to'] ?? null, fn ($query, $to) => $query->whereDate('borrow_date', '<=', $to))
            ->when($filters['asset_id'] ?? null, fn ($query, $assetId) => $query->where('asset_id', $assetId));

        Search::apply(
            $query,
            $filters['search'] ?? null,
            ['loans.borrower_name', 'loans.borrower_unit'],
            fn ($query, string $pattern) => $query->orWhereHas('asset', fn ($asset) => $asset->where(
                fn ($asset) => $asset->whereRaw(Search::likeSql('assets.code'), [$pattern])->orWhereRaw(Search::likeSql('assets.name'), [$pattern]),
            )),
        );
    }

    /**
     * Urutan daftar: Direncanakan (tanggal rencana terdekat), lalu aktif (tempo terdekat), lalu terbaru.
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function listOrder(Builder $query): void
    {
        $planned = LoanStatus::Direncanakan->value;
        $active = LoanStatus::Dipinjam->value;

        $query
            ->orderByRaw('case when status = ? then 0 when status = ? then 1 else 2 end', [$planned, $active])
            ->orderByRaw('case when status = ? then borrow_date end asc', [$planned])
            ->orderByRaw('case when status = ? then due_date end asc', [$active])
            ->orderByDesc('borrow_date')
            ->orderByDesc('id');
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private static function flag(array $filters, string $key): bool
    {
        return filter_var($filters[$key] ?? false, FILTER_VALIDATE_BOOLEAN);
    }

    /**
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function overdue(Builder $query): void
    {
        $query->where('status', LoanStatus::Dipinjam->value)->whereDate('due_date', '<', today());
    }

    /**
     * Jadwal penyerahan: Direncanakan bertanggal hari ini atau sudah lewat (Belum diserahkan).
     *
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function handoverDue(Builder $query): void
    {
        $query->where('status', LoanStatus::Direncanakan->value)->whereDate('borrow_date', '<=', today());
    }

    /**
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function dueToday(Builder $query): void
    {
        $query->where('status', LoanStatus::Dipinjam->value)->whereDate('due_date', today());
    }
}
