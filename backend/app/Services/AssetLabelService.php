<?php

namespace App\Services;

use App\Models\Asset;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

/**
 * PDF label QR aset: QR 5×5 cm berisi link halaman publik + kode, grid 3 kolom × 4 baris per A4.
 */
class AssetLabelService
{
    public const COLUMNS = 3;

    public const ROWS = 4;

    public const MAX_LABELS = 120;

    public function __construct(private QrCodeService $qr) {}

    /**
     * @param  Collection<int, Asset>  $assets
     */
    public function pdf(Collection $assets): DomPdf
    {
        return Pdf::loadView('pdf.asset-labels', ['pages' => $this->pages($assets)])
            ->setPaper('a4', 'portrait');
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
