{{-- Label QR aset (SPEC §6): QR 5×5 cm + kode kecil, grid 3×4 per halaman A4, garis potong putus-putus. --}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Label QR Aset</title>
    <style>
        @page { size: A4 portrait; margin: 10mm; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; color: #000; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        table.sheet { border-collapse: collapse; }
        table.sheet td { width: 60mm; height: 64mm; padding: 0; vertical-align: top; }
        .label { width: 50mm; padding: 1mm; border: 0.2mm dashed #888; text-align: center; }
        .label img { display: block; margin: 0 auto; width: 50mm; height: 50mm; }
        .code { margin-top: 1mm; font-size: 9pt; font-weight: bold; letter-spacing: 0.3mm; }
        .text { margin-top: 0.5mm; font-size: 6pt; line-height: 1.1; }
    </style>
</head>
<body>
@foreach ($pages as $rows)
    <div class="page">
        <table class="sheet">
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $label)
                        <td>
                            <div class="label">
                                <img src="{{ $label['qr'] }}" alt="QR {{ $label['code'] }}">
                                <div class="code">{{ $label['code'] }}</div>
                                @if (($text ?? '') !== '')
                                    <div class="text">{{ $text }}</div>
                                @endif
                            </div>
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </table>
    </div>
@endforeach
</body>
</html>
