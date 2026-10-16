<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Support\Search;
use Database\Factories\AssetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['code', 'asset_type_id', 'name', 'brand', 'serial_number', 'specification', 'source_department_id', 'room_id', 'pic_name', 'finance_code', 'acquisition_date', 'next_service_date', 'condition', 'status', 'note', 'created_by'])]
class Asset extends Model
{
    public const SERVICE_WARNING_DAYS = 14;

    /** @use HasFactory<AssetFactory> */
    use HasFactory;

    public const PUBLIC_TOKEN_LENGTH = 12;

    /**
     * public_token dibuat sekali saat aset dibuat dan tidak pernah berubah (tidak fillable).
     */
    protected static function booted(): void
    {
        static::creating(function (Asset $asset) {
            do {
                $token = Str::random(self::PUBLIC_TOKEN_LENGTH);
            } while (static::query()->where('public_token', $token)->exists());

            $asset->public_token = $token;
        });

        static::updating(function (Asset $asset) {
            if ($asset->isDirty('public_token')) {
                $asset->public_token = $asset->getOriginal('public_token');
            }
        });
    }

    /**
     * Isi QR label: link halaman publik aset (SPEC §6).
     */
    public function publicUrl(): string
    {
        return config('app.qr_base_url').'/aset/'.$this->public_token;
    }

    /**
     * Lokasi saat ini: tujuan peminjaman aktif (ruangan atau catatan bebas), selain itu lokasi asal.
     *
     * @return array{on_loan: bool, room: ?Room, note: ?string}
     */
    public function currentLocation(): array
    {
        $loan = $this->status === AssetStatus::Dipinjam ? $this->activeLoan : null;

        if ($loan === null) {
            return ['on_loan' => false, 'room' => $this->room, 'note' => null];
        }

        return ['on_loan' => true, 'room' => $loan->destinationRoom, 'note' => $loan->destination_note];
    }

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'condition' => AssetCondition::Baik->value,
        'status' => AssetStatus::Tersedia->value,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'acquisition_date' => 'date',
            'next_service_date' => 'date',
            'condition' => AssetCondition::class,
            'status' => AssetStatus::class,
        ];
    }

    /**
     * @return BelongsTo<AssetType, $this>
     */
    public function assetType(): BelongsTo
    {
        return $this->belongsTo(AssetType::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function sourceDepartment(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'source_department_id');
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<AssetEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    /**
     * Kode lama (saat jenis diganti), terbaru dulu.
     *
     * @return HasMany<AssetCodeHistory, $this>
     */
    public function codeHistories(): HasMany
    {
        return $this->hasMany(AssetCodeHistory::class)->latest('replaced_at')->latest('id');
    }

    /**
     * @return HasMany<AssetFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(AssetFile::class);
    }

    /**
     * @return HasMany<Loan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(Loan::class);
    }

    /**
     * Transaksi peminjaman terakhir (untuk "Hilang saat dipinjam oleh ...").
     *
     * @return HasOne<Loan, $this>
     */
    public function latestLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->latestOfMany();
    }

    /**
     * Loan berstatus Dipinjam (maksimal satu per aset).
     *
     * @return HasOne<Loan, $this>
     */
    public function activeLoan(): HasOne
    {
        return $this->hasOne(Loan::class)->where('status', LoanStatus::Dipinjam->value);
    }

    /**
     * Filter Daftar Aset (SPEC §5); dipakai daftar & laporan. Tidak Layak Pakai tetap ikut.
     *
     * @param  Builder<Asset>  $query
     * @param  array<string, mixed>  $filters
     */
    #[Scope]
    protected function filter(Builder $query, array $filters): void
    {
        $query
            ->when($filters['category_id'] ?? null, fn ($query, $id) => $query->whereHas('assetType', fn ($type) => $type->where('category_id', $id)))
            ->when($filters['building_id'] ?? null, fn ($query, $id) => $query->whereHas('room', fn ($room) => $room->where('building_id', $id)))
            ->when($filters['room_id'] ?? null, fn ($query, $id) => $query->where('room_id', $id))
            ->when($filters['source_department_id'] ?? null, fn ($query, $id) => $query->where('source_department_id', $id))
            ->when($filters['condition'] ?? null, fn ($query, $condition) => $query->where('condition', $condition))
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status));

        Search::apply(
            $query,
            $filters['search'] ?? null,
            ['assets.name', 'assets.code', 'assets.serial_number'],
            fn ($query, string $pattern) => $query->orWhereHas(
                'codeHistories',
                fn ($history) => $history->whereRaw(Search::likeSql('asset_code_histories.code'), [$pattern]),
            ),
        );
    }

    /**
     * Perlu servis (SPEC §5): tanggal servis berikutnya sudah lewat atau ≤ 14 hari lagi.
     * Aset Hilang / Tidak Layak Pakai tidak dihitung (tidak bisa diservis); Dipinjam & Perbaikan tetap.
     *
     * @param  Builder<Asset>  $query
     */
    #[Scope]
    protected function needsService(Builder $query): void
    {
        $query->whereNotNull('next_service_date')
            ->whereDate('next_service_date', '<=', today()->addDays(self::SERVICE_WARNING_DAYS))
            ->whereNotIn('status', [AssetStatus::Hilang->value, AssetStatus::TidakLayakPakai->value]);
    }
}
