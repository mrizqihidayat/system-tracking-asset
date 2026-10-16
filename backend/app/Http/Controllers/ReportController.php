<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexReportRequest;
use App\Services\ReportService;
use App\Services\XlsxReportWriter;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    private const XLSX_MIME = 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';

    /**
     * Unduh laporan Excel (SPEC §12); filter sama dengan daftar di layar.
     */
    public function show(IndexReportRequest $request, ReportService $reports, XlsxReportWriter $writer): StreamedResponse
    {
        $filters = collect($request->validated())->except('format')->all();

        $report = $reports->build($request->reportType(), $filters);
        $path = $writer->write($report, $request->user());

        return response()->streamDownload(function () use ($path) {
            readfile($path);
            @unlink($path);
        }, $report->filename(), ['Content-Type' => self::XLSX_MIME]);
    }
}
