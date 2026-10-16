<?php

namespace App\Services;

use App\Models\User;
use App\Reports\Report;
use App\Reports\ReportColumn;
use App\Reports\ReportSheet;
use DateTimeImmutable;
use DateTimeInterface;
use OpenSpout\Common\Entity\Cell;
use OpenSpout\Common\Entity\Cell\StringCell;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * Menulis Report ke file .xlsx (streaming). Teks SELALU ditulis sebagai string (StringCell), tidak lewat
 * Cell::fromValue(), supaya data yang diawali "=" tidak menjadi rumus (formula injection).
 */
class XlsxReportWriter
{
    /** Baris judul (1), filter (2), dicetak (3), kosong (4), header (5); data mulai baris 6. */
    private const HEADER_ROW = 5;

    private const DATE_FORMAT = 'dd/mm/yyyy';

    private const DATETIME_FORMAT = 'dd/mm/yyyy hh:mm';

    /**
     * @return string path file sementara (hapus setelah dipakai)
     */
    public function write(Report $report, User $user): string
    {
        $path = tempnam(sys_get_temp_dir(), 'report');
        $writer = new Writer;
        $writer->setCreator(config('app.name'));
        $writer->openToFile($path);

        foreach ($report->sheets as $index => $sheet) {
            $worksheet = $index === 0 ? $writer->getCurrentSheet() : $writer->addNewSheetAndMakeItCurrent();
            $worksheet->setName($sheet->name);
            $worksheet->setSheetView((new SheetView)->setFreezeRow(self::HEADER_ROW + 1));

            foreach ($sheet->columns as $column => $definition) {
                $worksheet->setColumnWidth($definition->width, $column + 1);
            }

            $this->writeSheet($writer, $report, $sheet, $user);
        }

        $writer->close();

        return $path;
    }

    private function writeSheet(Writer $writer, Report $report, ReportSheet $sheet, User $user): void
    {
        $title = (new Style)->setFontBold()->setFontSize(14);
        $header = (new Style)->setFontBold()->setBackgroundColor('D9E2F3')->setShouldWrapText();

        $writer->addRow($this->textRow([$report->title().' — '.$sheet->name], $title));
        $writer->addRow($this->textRow([$report->filterSummary]));
        $writer->addRow($this->textRow(['Dicetak '.now()->format('d/m/Y H:i').' oleh '.$user->name]));
        $writer->addRow(new Row([]));
        $writer->addRow($this->textRow(array_map(fn (ReportColumn $column) => $column->header, $sheet->columns), $header));

        $styles = [
            ReportColumn::DATE => (new Style)->setFormat(self::DATE_FORMAT),
            ReportColumn::DATETIME => (new Style)->setFormat(self::DATETIME_FORMAT),
        ];

        foreach ($sheet->rows as $values) {
            $cells = [];

            foreach ($sheet->columns as $index => $column) {
                $cells[] = $this->cell($values[$index] ?? null, $column, $styles[$column->type] ?? null);
            }

            $writer->addRow(new Row($cells));
        }
    }

    private function cell(mixed $value, ReportColumn $column, ?Style $style): Cell
    {
        if ($value === null || $value === '') {
            return Cell::fromValue(null);
        }

        if ($column->type === ReportColumn::INT) {
            return Cell::fromValue((int) $value);
        }

        if ($value instanceof DateTimeInterface) {
            return Cell::fromValue(DateTimeImmutable::createFromInterface($value), $style);
        }

        return new StringCell((string) $value, null);
    }

    /**
     * @param  list<string>  $values
     */
    private function textRow(array $values, ?Style $style = null): Row
    {
        return new Row(array_map(fn (string $value) => new StringCell($value, $style), $values));
    }
}
