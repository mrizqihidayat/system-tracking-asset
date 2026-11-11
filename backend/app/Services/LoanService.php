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
     * Pinjam atau pesan. Tanggal pinjam > hari ini → Direncanakan (aset tidak berubah, tanpa riwayat aset;
     * boleh untuk aset Tersedia/Dipinjam/Perbaikan). Hari ini/lampau → Dipinjam (aset harus Tersedia).
     * Periode [tanggal pinjam, rencana kembali] tidak boleh bentrok dengan transaksi Direncanakan/Dipinjam lain.
     * Kondisi keluar = kondisi aset saat ini (tidak diinput staf; diperbarui saat Serahkan).
     *
     * @param  array<string, mixed>  $data  data tervalidasi StoreLoanRequest
     */
    public function borrow(array $data, User $actor): Loan
    {
        return DB::transaction(function () use ($data, $actor) {
            $asset = Asset::query()->whereKey($data['asset_id'])->lockForUpdate()->firstOrFail();
            $borrowDate = Carbon::parse($data['borrow_date'] ?? today())->startOfDay();
            $dueDate = Carbon::parse($data['due_date'])->startOfDay();
            $planned = $borrowDate->gt(today());

            $planned ? $this->ensurePlannable($asset) : $this->ensureBorrowable($asset);

            if ($borrowDate->lt($asset->created_at->copy()->startOfDay())) {
                throw ValidationException::withMessages(['borrow_date' => 'Tanggal pinjam tidak boleh sebelum aset terdaftar.']);
            }

            $this->ensureNoConflict($asset, $borrowDate, $dueDate, 'borrow_date');

            try {
                $loan = Loan::create([
                    ...$data,
                    'borrow_date' => $borrowDate,
                    'condition_out' => $asset->condition,
                    'borrowed_by' => $actor->id,
                    'status' => $planned ? LoanStatus::Direncanakan : LoanStatus::Dipinjam,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['asset_id' => 'Aset sedang dipinjam.']);
            }

            if (! $planned) {
                $this->startLoan($asset, $loan, $borrowDate, $actor);
            }

            return $loan;
        });
    }

    /**
     * Serahkan pemesanan: dicatat saat barang benar-benar diserahkan (boleh sebelum/pada/setelah tanggal rencana).
     * Aset harus Tersedia; periode [tanggal penyerahan, rencana kembali] dicek ulang terhadap bentrok.
     */
    public function handOver(Loan $loan, ?string $handoverDate, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $handoverDate, $actor) {
            [$loan, $asset] = $this->lockWithStatus($loan, LoanStatus::Direncanakan, 'Transaksi tidak berstatus Direncanakan.');
            $date = Carbon::parse($handoverDate ?? today())->startOfDay();

            $this->ensureBorrowable($asset);

            if ($date->lt($asset->created_at->copy()->startOfDay())) {
                throw ValidationException::withMessages(['handover_date' => 'Tanggal penyerahan tidak boleh sebelum aset terdaftar.']);
            }

            if ($date->gt($loan->due_date)) {
                throw ValidationException::withMessages(['handover_date' => 'Tanggal penyerahan tidak boleh setelah rencana kembali.']);
            }

            $this->ensureNoConflict($asset, $date, $loan->due_date, 'handover_date', $loan->id);

            try {
                $loan->update([
                    'status' => LoanStatus::Dipinjam,
                    'handover_date' => $date,
                    'condition_out' => $asset->condition,
                ]);
            } catch (UniqueConstraintViolationException) {
                throw ValidationException::withMessages(['loan' => 'Aset sedang dipinjam.']);
            }

            $this->startLoan($asset, $loan, $date, $actor);

            return $loan;
        });
    }

    /**
     * Status aset → Dipinjam + riwayat "Dipinjam" (pihak = peminjam, bertanggal pinjam/penyerahan).
     */
    private function startLoan(Asset $asset, Loan $loan, CarbonInterface $date, User $actor): void
    {
        $asset->update(['status' => AssetStatus::Dipinjam]);

        $this->events->record($asset, EventType::Dipinjam, $actor, [
            'event_date' => $this->eventDate($date),
            'party' => $loan->borrower_name,
            'condition' => $asset->condition,
            'loan_id' => $loan->id,
            'to_room_id' => $loan->destination_room_id,
            'note' => $loan->purpose,
        ]);
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

            $returnDate = $this->returnDate($data['return_date'] ?? null);
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
            $date = $this->returnDate($returnDate);

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
     * Batalkan transaksi Dipinjam (status aset mengikuti kondisi aset saat ini, A6, + riwayat "Dibatalkan")
     * atau pemesanan Direncanakan (alasan wajib, aset tidak berubah, tanpa riwayat aset).
     */
    public function cancel(Loan $loan, string $reason, User $actor): Loan
    {
        return DB::transaction(function () use ($loan, $reason, $actor) {
            $message = 'Transaksi tidak berstatus Dipinjam atau Direncanakan.';
            $status = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->value('status');

            if ($status === LoanStatus::Direncanakan) {
                [$loan] = $this->lockWithStatus($loan, LoanStatus::Direncanakan, $message);
                $loan->update(['status' => LoanStatus::Dibatalkan, 'cancel_reason' => $reason]);

                return $loan;
            }

            [$loan, $asset] = $this->lockWithStatus($loan, LoanStatus::Dipinjam, $message);

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
     * Pemesanan masa depan: aset Hilang/Tidak Layak Pakai ditolak; Dipinjam/Perbaikan boleh (dicek lagi saat Serahkan).
     */
    private function ensurePlannable(Asset $asset): void
    {
        if (in_array($asset->status, [AssetStatus::Hilang, AssetStatus::TidakLayakPakai], true)) {
            throw ValidationException::withMessages(['asset_id' => "Aset berstatus {$asset->status->value}, tidak bisa dipesan."]);
        }
    }

    /**
     * Periode [start, end] (tanggal, inklusif) tidak boleh tumpang tindih dengan transaksi Direncanakan/Dipinjam lain
     * pada aset yang sama. Dipanggil setelah baris aset dikunci, sehingga pemesanan bersamaan berurutan.
     */
    private function ensureNoConflict(Asset $asset, CarbonInterface $start, CarbonInterface $end, string $field, ?int $exceptLoanId = null): void
    {
        $conflict = Loan::query()
            ->where('asset_id', $asset->id)
            ->whereIn('status', LoanStatus::occupying())
            ->when($exceptLoanId, fn ($query) => $query->whereKeyNot($exceptLoanId))
            ->orderBy('borrow_date')
            ->get()
            ->first(function (Loan $other) use ($start, $end) {
                [$otherStart, $otherEnd] = $other->occupiedPeriod();

                return $otherStart->lte($end) && $otherEnd->gte($start);
            });

        if ($conflict === null) {
            return;
        }

        [$from, $to] = $conflict->occupiedPeriod();
        $label = $conflict->status === LoanStatus::Direncanakan ? 'pemesanan' : 'peminjaman aktif';

        throw ValidationException::withMessages([
            $field => "Bentrok dengan {$label} {$conflict->borrower_name} ({$from->format('d/m/Y')}–{$to->format('d/m/Y')}).",
        ]);
    }

    /**
     * Kunci transaksi & asetnya; transaksi harus masih Dipinjam.
     *
     * @return array{0: Loan, 1: Asset}
     */
    private function lockActive(Loan $loan): array
    {
        return $this->lockWithStatus($loan, LoanStatus::Dipinjam, 'Transaksi tidak berstatus Dipinjam.');
    }

    /**
     * Kunci transaksi & asetnya; transaksi harus berstatus $status.
     *
     * @return array{0: Loan, 1: Asset}
     */
    private function lockWithStatus(Loan $loan, LoanStatus $status, string $message): array
    {
        $loan = Loan::query()->whereKey($loan->getKey())->lockForUpdate()->firstOrFail();

        if ($loan->status !== $status) {
            throw ValidationException::withMessages(['loan' => $message]);
        }

        $asset = Asset::query()->whereKey($loan->asset_id)->lockForUpdate()->firstOrFail();

        return [$loan, $asset];
    }

    private function returnDate(?string $value): CarbonInterface
    {
        return $value ? Carbon::parse($value) : now();
    }

    /**
     * Tanggal saja: hari ini → waktu sekarang, tanggal lampau → awal hari itu.
     */
    private function eventDate(CarbonInterface $date): CarbonInterface
    {
        return $date->isToday() ? now() : $date;
    }
}
