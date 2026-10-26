<?php

namespace App\Services;

use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;

/**
 * Satu-satunya tempat yang memakai library QR (chillerlan/php-qrcode, butuh ekstensi GD).
 * PNG dipilih karena paling aman dirender dompdf.
 */
class QrCodeService
{
    /**
     * Byte PNG mentah QR (untuk respons HTTP image/png).
     */
    public function png(string $content): string
    {
        $uri = $this->pngDataUri($content);

        return base64_decode(substr($uri, strlen('data:image/png;base64,')));
    }

    /**
     * PNG QR sebagai data URI (data:image/png;base64,...). ECC M: tahan goresan ringan pada label.
     */
    public function pngDataUri(string $content): string
    {
        $options = new QROptions([
            'outputType' => QROutputInterface::GDIMAGE_PNG,
            'eccLevel' => EccLevel::M,
            'scale' => 10,
            'addQuietzone' => true,
            'quietzoneSize' => 2,
            'outputBase64' => true,
        ]);

        return (new QRCode($options))->render($content);
    }
}
