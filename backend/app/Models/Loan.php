<?php

namespace App\Models;

use App\Enums\AssetCondition;
use App\Enums\DamageConfirmation;
use App\Enums\LoanStatus;
use Database\Factories\LoanFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['asset_id', 'borrower_name', 'borrower_unit', 'purpose', 'destination_room_id', 'destination_note', 'borrow_date', 'due_date', 'condition_out', 'approval_number', 'approval_date', 'borrowed_by', 'status', 'return_date', 'return_condition', 'return_room_id', 'received_by', 'damage_confirmation', 'damage_confirmed_at', 'damage_confirmed_by', 'damage_confirmation_note', 'damage_confirmation_document', 'cancel_reason', 'note'])]
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
            'due_date' => 'date',
            'approval_date' => 'date',
            'return_date' => 'datetime',
            'damage_confirmed_at' => 'datetime',
            'condition_out' => AssetCondition::class,
            'return_condition' => AssetCondition::class,
            'status' => LoanStatus::class,
            'damage_confirmation' => DamageConfirmation::class,
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
     * @return BelongsTo<User, $this>
     */
    public function damageConfirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'damage_confirmed_by');
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
     * Jatuh tempo hari ini = Dipinjam dan rencana kembali = hari ini.
     */
    public function isDueToday(): bool
    {
        return $this->status === LoanStatus::Dipinjam && $this->due_date->isSameDay(today());
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
     * @param  Builder<Loan>  $query
     */
    #[Scope]
    protected function dueToday(Builder $query): void
    {
        $query->where('status', LoanStatus::Dipinjam->value)->whereDate('due_date', today());
    }
}
