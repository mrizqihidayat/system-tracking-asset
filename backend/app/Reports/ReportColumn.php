<?php

namespace App\Reports;

/**
 * Kolom laporan Excel: tipe menentukan format sel (teks selalu string, tidak pernah rumus).
 */
final class ReportColumn
{
    public const TEXT = 'text';

    public const INT = 'int';

    public const DATE = 'date';

    public const DATETIME = 'datetime';

    public function __construct(
        public readonly string $header,
        public readonly float $width = 18,
        public readonly string $type = self::TEXT,
    ) {}

    public static function text(string $header, float $width = 18): self
    {
        return new self($header, $width, self::TEXT);
    }

    public static function int(string $header, float $width = 10): self
    {
        return new self($header, $width, self::INT);
    }

    public static function date(string $header, float $width = 14): self
    {
        return new self($header, $width, self::DATE);
    }

    public static function datetime(string $header, float $width = 18): self
    {
        return new self($header, $width, self::DATETIME);
    }
}
