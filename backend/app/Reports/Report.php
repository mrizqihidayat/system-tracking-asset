<?php

namespace App\Reports;

use App\Enums\ReportType;

final class Report
{
    /**
     * @param  list<ReportSheet>  $sheets
     */
    public function __construct(
        public readonly ReportType $type,
        public readonly string $filterSummary,
        public readonly array $sheets,
    ) {}

    public function title(): string
    {
        return $this->type->title();
    }

    public function filename(): string
    {
        return 'laporan-'.$this->type->slug().'-'.now()->format('Ymd').'.xlsx';
    }
}
