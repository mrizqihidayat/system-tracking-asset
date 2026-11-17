<?php

namespace App\Services;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * PDF label QR aset: QR 5×5 cm berisi link halaman publik + kode, grid 3 kolom × 4 baris per A4.
 * Teks label dari Pengaturan (T40, maks. 40 karakter) dicetak di bawah kode; kosong = tidak tampil.
 */
class AssetLabelService
{
    public const COLUMNS = 3;

    public const ROWS = 4;

    public const MAX_LABELS = 120;

    public function __construct(private QrCodeService $qr, private SettingService $settings) {}

    /**
     * @param  Collection<int, Asset>  $assets
     */
    public function pdf(Collection $assets): DomPdf
    {
        return Pdf::loadView('pdf.asset-labels', $this->viewData($assets))->setPaper('a4', 'portrait');
    }

    /**
     * @param  Collection<int, Asset>  $assets
     * @return array{pages: Collection<int, mixed>, text: string}
     */
    public function viewData(Collection $assets): array
    {
        return ['pages' => $this->pages($assets), 'text' => $this->settings->text('label_text')];
    }

    /**
     * Label dikelompokkan per halaman lalu per baris.
     *
     * @param  Collection<int, Asset>  $assets
     * @return Collection<int, Collection<int, Collection<int, array{code: string, qr: string}>>>
     */
    public function pages(Collection $assets): Collection
    {
        return $assets
            ->map(fn (Asset $asset) => [
                'code' => $asset->code,
                'qr' => $this->qr->pngDataUri($asset->publicUrl()),
            ])
            ->values()
            ->chunk(self::COLUMNS * self::ROWS)
            ->map(fn (Collection $page) => $page->values()->chunk(self::COLUMNS));
    }
}
