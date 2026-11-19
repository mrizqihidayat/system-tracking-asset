<?php

namespace App\Services;

use App\Enums\CountMethod;
use App\Enums\ReportType;
use App\Models\Asset;
use App\Models\AssetType;
use App\Models\Building;
use App\Models\Category;
use App\Models\Consumable;
use App\Models\Department;
use App\Models\Loan;
use App\Models\Room;
use App\Models\RoomStandard;
use App\Models\StockTransaction;
use App\Models\SupplyRequest;
use App\Reports\Report;
use App\Reports\ReportColumn;
use App\Reports\ReportSheet;
use BackedEnum;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Laporan Excel (SPEC §12). Filter memakai scope model yang sama dengan daftar di layar
 * (Asset/Loan/Consumable/SupplyRequest::filter()), baris dibaca lazy per 500 agar memori kecil.
 */
class ReportService
{
    private const CHUNK = 500;

    public function __construct(private RoomReadinessService $readiness) {}

    /**
     * @param  array<string, mixed>  $filters  hasil validasi IndexReportRequest
     */
    public function build(ReportType $type, array $filters): Report
    {
        return match ($type) {
            ReportType::Assets => $this->assets($filters),
            ReportType::Loans => $this->loans($filters),
            ReportType::Rooms => $this->rooms($filters),
            ReportType::Requests => $this->requests($filters),
            ReportType::Consumables => $this->consumables($filters),
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function assets(array $filters): Report
    {
        $rows = Asset::query()
            ->with(['assetType.category', 'sourceDepartment', 'room.building'])
            ->filter($filters)
            ->orderBy('code')
            ->lazy(self::CHUNK)
            ->map(fn (Asset $asset) => [
                $asset->code,
                $asset->name,
                $asset->assetType->category->name,
                $asset->assetType->name,
                $asset->brand,
                $asset->model,
                $asset->serial_number,
                $asset->sourceDepartment->name,
                $asset->room->building->name,
                $asset->room->name,
                $asset->pic_name,
                $this->value($asset->condition),
                $this->value($asset->status),
                $asset->acquisition_date,
                $asset->next_service_date,
                $asset->finance_code,
            ]);

        $columns = [
            ReportColumn::text('Kode', 16), ReportColumn::text('Nama', 30),
            ReportColumn::text('Kategori', 16), ReportColumn::text('Jenis', 18), ReportColumn::text('Merek', 16), ReportColumn::text('Model', 16), ReportColumn::text('No. Seri', 18),
            ReportColumn::text('Sumber', 14), ReportColumn::text('Gedung (asal)', 18), ReportColumn::text('Ruangan (asal)', 18),
            ReportColumn::text('PIC', 18), ReportColumn::text('Kondisi', 12), ReportColumn::text('Status', 16),
            ReportColumn::date('Tgl perolehan'), ReportColumn::date('Servis berikutnya'), ReportColumn::text('Kode aset keuangan', 20),
        ];

        $summary = $this->summary([
            'Kategori' => $this->nameOf(Category::class, $filters['category_id'] ?? null),
            'Jenis' => $this->nameOf(AssetType::class, $filters['asset_type_id'] ?? null),
            'Gedung' => $this->nameOf(Building::class, $filters['building_id'] ?? null),
            'Ruangan' => $this->nameOf(Room::class, $filters['room_id'] ?? null),
            'Sumber' => $this->nameOf(Department::class, $filters['source_department_id'] ?? null),
            'Kondisi' => $filters['condition'] ?? null,
            'Status' => $filters['status'] ?? null,
            'Pencarian' => $filters['search'] ?? null,
        ]);

        return new Report(ReportType::Assets, $summary, [new ReportSheet('Aset', $columns, $rows)]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function loans(array $filters): Report
    {
        $rows = Loan::query()
            ->with(['asset', 'destinationRoom.building'])
            ->filter($filters)
            ->listOrder()
            ->lazy(self::CHUNK)
            ->map(fn (Loan $loan) => [
                $loan->asset->code,
                $loan->asset->name,
                $loan->borrower_name,
                $loan->borrower_unit,
                $loan->purpose,
                $loan->destinationRoom
                    ? "{$loan->destinationRoom->building->name} / {$loan->destinationRoom->name}"
                    : $loan->destination_note,
                $loan->borrow_date,
                $loan->due_date,
                $loan->return_date,
                $this->value($loan->status),
                $loan->isOverdue() ? 'Ya' : 'Tidak',
                $this->value($loan->condition_out),
                $this->value($loan->return_condition),
                $loan->note,
            ]);

        $columns = [
            ReportColumn::text('Kode aset', 16), ReportColumn::text('Nama aset', 28), ReportColumn::text('Peminjam', 22),
            ReportColumn::text('Departemen', 14), ReportColumn::text('Keperluan', 28), ReportColumn::text('Tujuan', 28),
            ReportColumn::date('Tgl pinjam'), ReportColumn::date('Rencana kembali'), ReportColumn::datetime('Tgl kembali'),
            ReportColumn::text('Status', 14), ReportColumn::text('Terlambat', 11), ReportColumn::text('Kondisi keluar', 14),
            ReportColumn::text('Kondisi kembali', 14), ReportColumn::text('Catatan', 30),
        ];

        $summary = $this->summary([
            'Periode' => $this->period($filters, 'tanggal pinjam'),
            'Status' => $filters['status'] ?? null,
            'Terlambat' => $this->flag($filters, 'overdue'),
            'Jatuh tempo hari ini' => $this->flag($filters, 'due_today'),
            'Aset' => isset($filters['asset_id']) ? Asset::query()->whereKey($filters['asset_id'])->value('code') : null,
            'Pencarian' => $filters['search'] ?? null,
        ]);

        return new Report(ReportType::Loans, $summary, [new ReportSheet('Peminjaman', $columns, $rows)]);
    }

    /**
     * Sheet Ringkasan (per ruangan) + Detail (aset per jenis & barang habis pakai). Ruangan tanpa standar tetap muncul.
     *
     * @param  array<string, mixed>  $filters
     */
    private function rooms(array $filters): Report
    {
        $buildings = $this->readiness->readiness()
            ->when($filters['building_id'] ?? null, fn (EloquentCollection $all, $id) => $all->where('id', (int) $id))
            ->values();

        $standards = new EloquentCollection($buildings->flatMap(fn (Building $b) => $b->rooms->flatMap->standards)->all());
        $standards->load(RoomReadinessService::RELATIONS);

        $summaryRows = [];
        $detailRows = [];

        foreach ($buildings as $building) {
            foreach ($building->rooms as $room) {
                $summary = $this->readiness->summarize($room->standards);

                $summaryRows[] = [
                    $building->name,
                    $room->name,
                    $summary['total_items'],
                    $summary['shortage_items'],
                    $summary['unchecked_items'],
                    match (true) {
                        $room->is_storage => 'Gudang',
                        $summary['total_items'] === 0 => 'Belum ada standar',
                        $summary['shortage_items'] > 0 => "Kurang {$summary['shortage_items']} item",
                        default => 'Lengkap',
                    },
                    $room->last_checked_at,
                    $room->lastCheckedBy?->name,
                ];

                foreach ($this->readiness->assetGroups($room, $room->standards) as $group) {
                    $detailRows[] = [
                        $building->name, $room->name, 'Aset', $group['asset_type']['name'], $group['total'], $group['standard_quantity'],
                        $group['present_quantity'], $group['difference'], $group['result'], implode(', ', array_column($group['assets'], 'code')),
                    ];
                }

                foreach ($room->standards->where('count_method', CountMethod::Manual)->sortBy('id') as $standard) {
                    $detailRows[] = $this->standardRow($building, $room, $standard);
                }
            }
        }

        $summaryColumns = [
            ReportColumn::text('Gedung', 20), ReportColumn::text('Ruangan', 22), ReportColumn::int('Total item'),
            ReportColumn::int('Kurang'), ReportColumn::int('Belum dicek'), ReportColumn::text('Kesiapan', 20),
            ReportColumn::datetime('Terakhir dicek'), ReportColumn::text('Dicek oleh', 18),
        ];

        $detailColumns = [
            ReportColumn::text('Gedung', 20), ReportColumn::text('Ruangan', 22), ReportColumn::text('Bagian', 16),
            ReportColumn::text('Item', 28), ReportColumn::int('Jumlah aset'), ReportColumn::int('Standar'),
            ReportColumn::int('Ada'), ReportColumn::int('Selisih'), ReportColumn::text('Hasil', 12),
            ReportColumn::text('Kode aset / stok', 36),
        ];

        $summary = $this->summary(['Gedung' => $this->nameOf(Building::class, $filters['building_id'] ?? null)]);

        return new Report(ReportType::Rooms, $summary, [
            new ReportSheet('Ringkasan', $summaryColumns, $summaryRows),
            new ReportSheet('Detail', $detailColumns, $detailRows),
        ]);
    }

    /**
     * @return list<mixed>
     */
    private function standardRow(Building $building, Room $room, RoomStandard $standard): array
    {
        $present = $standard->presentQuantity();

        return [
            $building->name,
            $room->name,
            'Barang habis pakai',
            $standard->item_name,
            null,
            $standard->standard_quantity,
            $present,
            $present === null ? null : $present - $standard->standard_quantity,
            $standard->checklistResult()->value,
            "Stok: {$standard->consumable->quantity} {$standard->consumable->unit}",
        ];
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function requests(array $filters): Report
    {
        $rows = SupplyRequest::query()
            ->with(['catalog', 'providerDepartment', 'room.building', 'creator'])
            ->filter($filters)
            ->listOrder()
            ->lazy(self::CHUNK)
            ->map(fn (SupplyRequest $request) => [
                $request->request_date,
                $request->item_name,
                $request->quantity,
                $request->received_quantity,
                $request->purpose,
                $request->room ? "{$request->room->building->name} / {$request->room->name}" : null,
                $request->providerDepartment->name,
                $request->request_method,
                $request->isUnresponded() ? 'Belum direspons' : $request->status->value,
                $request->responseDueDate(),
                $request->received_at,
                $request->returned_at,
                $request->creator->name,
                $request->note,
            ]);

        $columns = [
            ReportColumn::date('Tanggal'), ReportColumn::text('Item', 28), ReportColumn::int('Jumlah'), ReportColumn::int('Masuk stok'),
            ReportColumn::text('Untuk kegiatan', 28), ReportColumn::text('Ruangan', 26), ReportColumn::text('Departemen penyedia', 16),
            ReportColumn::text('Cara permintaan', 16), ReportColumn::text('Status', 16), ReportColumn::date('Batas respons'),
            ReportColumn::datetime('Diterima'), ReportColumn::datetime('Dikembalikan'), ReportColumn::text('Dicatat oleh', 18),
            ReportColumn::text('Catatan', 30),
        ];

        $summary = $this->summary([
            'Periode' => $this->period($filters, 'tanggal permintaan'),
            'Status' => $filters['status'] ?? null,
            'Belum direspons' => $this->flag($filters, 'unresponded'),
            'Departemen penyedia' => $this->nameOf(Department::class, $filters['provider_department_id'] ?? null),
            'Ruangan' => $this->nameOf(Room::class, $filters['room_id'] ?? null),
            'Pencarian' => $filters['search'] ?? null,
        ]);

        return new Report(ReportType::Requests, $summary, [new ReportSheet('Permintaan', $columns, $rows)]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function consumables(array $filters): Report
    {
        $rows = Consumable::query()
            ->with(['providerDepartment', 'consumableCategory', 'room.building'])
            ->filter($filters)
            ->orderBy('name')
            ->lazy(self::CHUNK)
            ->map(fn (Consumable $consumable) => [
                $consumable->name,
                $consumable->consumableCategory?->name,
                $consumable->unit,
                $consumable->quantity,
                $consumable->min_quantity,
                $consumable->status()->value,
                $consumable->providerDepartment->name,
                $consumable->room ? "{$consumable->room->name}, {$consumable->room->building->name}" : null,
                $consumable->note,
            ]);

        $columns = [
            ReportColumn::text('Nama', 28), ReportColumn::text('Kategori', 16), ReportColumn::text('Satuan', 10),
            ReportColumn::int('Jumlah'), ReportColumn::int('Minimum'), ReportColumn::text('Status', 12),
            ReportColumn::text('Minta ke', 16), ReportColumn::text('Lokasi simpan', 28), ReportColumn::text('Catatan', 30),
        ];

        $summary = $this->summary([
            'Status' => $filters['status'] ?? null,
            'Kategori' => $filters['category'] ?? null,
            'Minta ke' => $this->nameOf(Department::class, $filters['provider_department_id'] ?? null),
            'Gedung' => $this->nameOf(Building::class, $filters['building_id'] ?? null),
            'Ruangan' => $this->nameOf(Room::class, $filters['room_id'] ?? null),
            'Pencarian' => $filters['search'] ?? null,
        ]);

        $transactions = StockTransaction::query()
            ->with(['consumable', 'room.building', 'creator'])
            ->whereHas('consumable', fn ($query) => $query->filter($filters))
            ->orderBy('id')
            ->lazy(self::CHUNK)
            ->map(fn (StockTransaction $transaction) => [
                $transaction->transaction_date,
                $transaction->consumable->name,
                $transaction->type->value,
                $transaction->quantity,
                $transaction->balance_after,
                $transaction->reason,
                $transaction->note,
                $transaction->room ? "{$transaction->room->building->name} / {$transaction->room->name}" : null,
                $transaction->supply_request_id,
                $transaction->creator?->name,
            ]);

        $transactionColumns = [
            ReportColumn::date('Tanggal'), ReportColumn::text('Barang', 28), ReportColumn::text('Jenis', 14),
            ReportColumn::int('Jumlah'), ReportColumn::int('Saldo'), ReportColumn::text('Alasan', 14),
            ReportColumn::text('Keterangan', 30), ReportColumn::text('Ruangan', 28), ReportColumn::int('No. permintaan'),
            ReportColumn::text('Dicatat oleh', 18),
        ];

        return new Report(ReportType::Consumables, $summary, [
            new ReportSheet('Stok', $columns, $rows),
            new ReportSheet('Transaksi', $transactionColumns, $transactions),
        ]);
    }

    /**
     * @param  array<string, mixed>  $parts  label => nilai (kosong dilewati)
     */
    private function summary(array $parts): string
    {
        $text = collect($parts)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->map(fn ($value, string $label) => $label === 'Periode' ? $value : "{$label}: {$value}")
            ->implode(' · ');

        return $text === '' ? 'Tanpa filter' : $text;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function period(array $filters, string $dateLabel): string
    {
        $from = isset($filters['from']) ? Carbon::parse($filters['from'])->format('d/m/Y') : null;
        $to = isset($filters['to']) ? Carbon::parse($filters['to'])->format('d/m/Y') : null;

        return match (true) {
            $from !== null && $to !== null => "Periode {$dateLabel}: {$from} – {$to}",
            $from !== null => "Periode {$dateLabel}: mulai {$from}",
            $to !== null => "Periode {$dateLabel}: sampai {$to}",
            default => 'Semua periode',
        };
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function flag(array $filters, string $key): ?string
    {
        return filter_var($filters[$key] ?? false, FILTER_VALIDATE_BOOLEAN) ? 'Ya' : null;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function nameOf(string $model, mixed $id): ?string
    {
        return $id === null || $id === '' ? null : $model::query()->whereKey($id)->value('name');
    }

    private function value(mixed $value): mixed
    {
        return $value instanceof BackedEnum ? $value->value : $value;
    }
}
