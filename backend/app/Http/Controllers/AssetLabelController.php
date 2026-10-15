<?php

namespace App\Http\Controllers;

use App\Http\Requests\PrintAssetLabelsRequest;
use App\Models\Asset;
use App\Services\AssetLabelService;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssetLabelController extends Controller
{
    public function __construct(private AssetLabelService $labels) {}

    /**
     * PDF label satu aset (sel pertama halaman A4). ?download=1 untuk unduh.
     */
    public function show(Request $request, Asset $asset): Response
    {
        return $this->respond($request, $this->labels->pdf(collect([$asset])), "label-{$asset->code}.pdf");
    }

    /**
     * PDF label banyak aset (ids[], maks. 120), urut kode.
     */
    public function store(PrintAssetLabelsRequest $request): Response
    {
        $assets = Asset::query()->whereKey($request->validated('ids'))->orderBy('code')->get();

        return $this->respond($request, $this->labels->pdf($assets), 'label-aset-'.now()->format('Ymd').'.pdf');
    }

    private function respond(Request $request, DomPdf $pdf, string $filename): Response
    {
        return $request->boolean('download') ? $pdf->download($filename) : $pdf->stream($filename);
    }
}
