<?php

namespace App\Services;

use App\Enums\AssetCondition;
use App\Enums\AssetStatus;
use App\Enums\EventType;
use App\Enums\LoanStatus;
use App\Models\Asset;
use App\Models\Loan;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Peminjaman (SPEC §7). Semua di DB::transaction() + lockForUpdate() pada transaksi & aset;
 * riwayat aset lewat AssetEventService.
 */
class LoanService
{
    public function __construct(private AssetEventService $events) {}

    /**
     * Pinjam: aset harus Tersedia (bukan Perbaikan/Hilang/Tidak Layak Pakai); kondisi keluar = kondisi aset saat ini (tidak diinput staf).
     *
     * @param  array<string, mixed>  $data  data tervalidasi StoreLoanRequest
     */
    public function borrow(array $data, User $actor): Loan
    {
        return DB::transaction(function () use ($data, $actor) {
            $asset = Asset::query()->whereKey($data['asset_id'])->lockForUpdate()->firstOrFail();
            $borrowDate = Carbon::parse($data['borrow_date'] ?? today())->startOfDay();

            $this->ensureBorrowable($asset);

            if ($borrowDate->lt($asset->created_at->copy()->startOfDay())) {
                throw ValidationException::withMessages(['borrow_date' => 'Tanggal pinjam tidak boleh sebelum aset terdaftar.']);
            }

            try {
                $loan = Loan::create([
                    ...$data,
                    'borrow_date' => $borrowDate,
                    'condition_out' => $asset->condition,
                    'borrowed_by' => $actor->id,
                    'status' => LoanStatus::Dipinjam,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['asset_id' => 'Aset sedang dipinjam.']);
            }

            $asset->update(['status' => AssetStatus::Dipinjam]);

            $this->events->record($asset, EventType::Dipinjam, $actor, [
                'event_date' => $this->eventDate($borrowDate),
                'party' => $loan->borrower_name,
                'condition' => $asset->condition,
                'loan_id' => $loan->id,
                'to_room_id' => $loan->destination_room_id,
                'note' => $loan->purpose,
            ]);

            return $loan;
        });
    }

    /**
     * Terima kembali: kondisi & status aset aturan A6, lokasi asal ikut ruangan penyimpanan.
     * Rusak Berat boleh langsung Tidak Layak Pakai (riwayat terpisah, sama dengan catat tidak layak pakai).
     *
     * @param  array{return_date?: ?string, return_condition: string, status?: ?string, return_room_id?: ?int, note?: ?string}  $data
     */
    public function giveBack(Loan $loan, array $data, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $data, $actor) {
            [$loan, $asset] = $this->lockActive($loan);

            $returnDate = $this->returnDate($loan, $data['return_date'] ?? null);
            $condition = AssetCondition::from($data['return_condition']);
            $chosen = AssetStatus::tryFrom((string) ($data['status'] ?? ''));
            $unfit = $condition === AssetCondition::RusakBerat && $chosen === AssetStatus::TidakLayakPakai;
            $status = $unfit ? AssetStatus::TidakLayakPakai : $condition->resolveStatus($chosen);
            $fromRoomId = $asset->room_id;
            $returnRoomId = (int) ($data['return_room_id'] ?? $fromRoomId);
            $note = $data['note'] ?? null;

            $loan->update([
                'status' => LoanStatus::Dikembalikan,
                'return_date' => $returnDate,
                'return_condition' => $condition,
                'return_room_id' => $returnRoomId,
                'received_by' => $actor->id,
                'note' => $note,
            ]);

            $asset->update(['condition' => $condition, 'status' => $status, 'room_id' => $returnRoomId]);

            $this->events->record($asset, EventType::Dikembalikan, $actor, [
                'event_date' => $returnDate,
                'party' => $loan->borrower_name,
                'condition' => $condition,
                'loan_id' => $loan->id,
                'to_room_id' => $returnRoomId,
                'note' => $note,
            ]);

            if ($returnRoomId !== $fromRoomId) {
                $this->events->record($asset, EventType::PindahLokasi, $actor, [
                    'event_date' => $returnDate,
                    'party' => 'SHD',
                    'from_room_id' => $fromRoomId,
                    'to_room_id' => $returnRoomId,
                    'loan_id' => $loan->id,
                ]);
            }

            if ($unfit) {
                $this->events->record($asset, EventType::TidakLayakPakai, $actor, [
                    'event_date' => $returnDate,
                    'party' => 'SHD',
                    'condition' => $condition,
                    'loan_id' => $loan->id,
                    'note' => $note,
                ]);
            }

            return $loan;
        });
    }

    /**
     * Hilang saat dipinjam: transaksi ditutup berstatus Hilang (tanpa konfirmasi peminjam),
     * status aset Hilang, riwayat Lapor Hilang tertaut ke pinjaman dengan pihak = peminjam.
     */
    public function markLost(Loan $loan, ?string $returnDate, string $note, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $returnDate, $note, $actor) {
            [$loan, $asset] = $this->lockActive($loan);
            $date = $this->returnDate($loan, $returnDate);

            $loan->update([
                'status' => LoanStatus::Hilang,
                'return_date' => $date,
                'received_by' => $actor->id,
                'note' => $note,
            ]);

            $asset->update(['status' => AssetStatus::Hilang]);

            $this->events->record($asset, EventType::LaporHilang, $actor, [
                'event_date' => $date,
                'party' => $loan->borrower_name,
                'loan_id' => $loan->id,
                'note' => $note,
            ]);

            return $loan;
        });
    }

    /**
     * Batalkan: hanya transaksi Dipinjam; status aset mengikuti kondisi aset saat ini (A6).
     */
    public function cancel(Loan $loan, string $reason, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $reason, $actor) {
            [$loan, $asset] = $this->lockActive($loan);

            $loan->update(['status' => LoanStatus::Dibatalkan, 'cancel_reason' => $reason]);
            $asset->update(['status' => $asset->condition->resolveStatus()]);

            $this->events->record($asset, EventType::Dibatalkan, $actor, [
                'party' => $loan->borrower_name,
                'loan_id' => $loan->id,
                'note' => $reason,
            ]);

            return $loan;
        });
    }

    /**
     * Konfirmasi kerusakan (dilakukan di luar sistem, staf mencatat hasilnya).
     */
    private function ensureBorrowable(Asset $asset): void
    {
        $message = match (true) {
            $asset->status === AssetStatus::Dipinjam => 'Aset sedang dipinjam.',
            $asset->status !== AssetStatus::Tersedia => "Aset berstatus {$asset->status->value}, tidak bisa dipinjam.",
            default => null,
        };

        if ($message !== null) {
            throw ValidationException::withMessages(['asset_id' => $message]);
        }
    }

    /**
     * Kunci transaksi & asetnya; transaksi harus masih Dipinjam.
     *
     * @return array{0: Loan, 1: Asset}
     */
    private function lockActive(Loan $loan): array
    {
        $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

        if ($loan->status !== LoanStatus::Dipinjam) {
            throw ValidationException::withMessages(['loan' => 'Transaksi tidak berstatus Dipinjam.']);
        }

        $asset = Asset::query()->whereKey($loan->asset_id)->lockForUpdate()->firstOrFail();

        return [$loan, $asset];
    }

    /**
     * Tanggal pinjam boleh di masa depan: selama belum tiba, transaksi hanya bisa dibatalkan.
     */
    private function returnDate(Loan $loan, ?string $value): CarbonInterface
    {
        $date = $value ? Carbon::parse($value) : now();

        if ($date->copy()->startOfDay()->lt($loan->borrow_date)) {
            throw ValidationException::withMessages(['return_date' => 'Tanggal pinjam belum tiba. Batalkan peminjaman jika tidak jadi.']);
        }

        return $date;
    }

    /**
     * Tanggal saja: hari ini → waktu sekarang, tanggal lampau → awal hari itu.
     */
    private function eventDate(CarbonInterface $date): CarbonInterface
    {
        return $date->isToday() ? now() : $date;
    }
}
