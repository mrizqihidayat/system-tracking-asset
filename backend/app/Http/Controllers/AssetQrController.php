<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Services\QrCodeService;
use Illuminate\Http\Response;

class AssetQrController extends Controller
{
    public function __construct(private QrCodeService $qr) {}

    /**
     * Gambar PNG QR aset (isi = URL publik `{QR_BASE_URL}/aset/{public_token}`), untuk tampilan detail.
     */
    public function show(Asset $asset): Response
    {
        return response($this->qr->png($asset->publicUrl()), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'private, max-age=3600',
        ]);
    }
}
