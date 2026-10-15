<?php

namespace App\Http\Resources;

use App\Enums\AssetStatus;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\AssetCodeHistory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Data aset untuk staf (ber-auth). Relasi hanya disertakan jika sudah di-eager-load.
 *
 * @mixin Asset
 */
class AssetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'public_token' => $this->public_token,
            'public_url' => $this->publicUrl(),
            'name' => $this->name,
            'brand' => $this->brand,
            'serial_number' => $this->serial_number,
            'specification' => $this->specification,
            'asset_type' => AssetTypeResource::make($this->whenLoaded('assetType')),
            'source_department' => DepartmentResource::make($this->whenLoaded('sourceDepartment')),
            'room' => RoomResource::make($this->whenLoaded('room')),
            'current_location' => $this->when($this->relationLoaded('room') && $this->relationLoaded('activeLoan'), function () {
                $location = $this->currentLocation();

                return [
                    'on_loan' => $location['on_loan'],
                    'room' => $location['room'] ? RoomResource::make($location['room']) : null,
                    'note' => $location['note'],
                ];
            }),
            'active_loan' => $this->whenLoaded('activeLoan', fn () => $this->activeLoan ? [
                'id' => $this->activeLoan->id,
                'borrower_name' => $this->activeLoan->borrower_name,
                'borrower_unit' => $this->activeLoan->borrower_unit,
                'borrow_date' => $this->activeLoan->borrow_date->toDateString(),
                'due_date' => $this->activeLoan->due_date->toDateString(),
                'is_overdue' => $this->activeLoan->due_date->lt(today()),
            ] : null),
            'previous_codes' => $this->whenLoaded('codeHistories', fn () => $this->codeHistories->map(fn (AssetCodeHistory $history) => [
                'code' => $history->code,
                'replaced_at' => $history->replaced_at->toIso8601String(),
            ])),
            'pic_name' => $this->pic_name,
            'finance_code' => $this->finance_code,
            'acquisition_date' => $this->acquisition_date?->toDateString(),
            'next_service_date' => $this->next_service_date?->toDateString(),
            'condition' => $this->condition,
            'status' => $this->status,
            'note' => $this->note,
            'lost_during_loan' => $this->whenLoaded('latestLoan', fn () => $this->lostDuringLoan()),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }

    /**
     * "Hilang saat dipinjam oleh [nama], [unit], [tanggal]": hanya jika aset masih Hilang dan
     * transaksi terakhirnya berstatus Hilang. Hanya untuk staf (tidak ada di halaman publik).
     *
     * @return array{borrower_name: string, borrower_unit: string, date: ?string, loan_id: int}|null
     */
    private function lostDuringLoan(): ?array
    {
        $loan = $this->latestLoan;

        if ($this->status !== AssetStatus::Hilang || $loan?->status !== LoanStatus::Hilang) {
            return null;
        }

        return [
            'borrower_name' => $loan->borrower_name,
            'borrower_unit' => $loan->borrower_unit,
            'date' => $loan->return_date?->toDateString(),
            'loan_id' => $loan->id,
        ];
    }
}
