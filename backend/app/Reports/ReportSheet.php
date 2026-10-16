<?php

namespace App\Reports;

/**
 * Satu sheet: kolom + baris (iterable, boleh lazy agar data besar tidak dimuat sekaligus).
 */
final class ReportSheet
{
    /**
     * @param  list<ReportColumn>  $columns
     * @param  iterable<list<mixed>>  $rows  nilai per kolom: string|int|Carbon|null
     */
    public function __construct(
        public readonly string $name,
        public readonly array $columns,
        public readonly iterable $rows,
    ) {}
}
