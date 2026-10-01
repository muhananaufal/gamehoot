<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Exported CSV files open in Excel: a cell starting with = + - @ would run as a formula
 * (CSV injection), so it gets a leading apostrophe.
 */
final class CsvCell
{
    /**
     * Written first: without a byte order mark, Excel on Windows opens a double-clicked CSV
     * in the local code page and breaks accents and emoji in names.
     */
    public const string BOM = "\u{FEFF}";

    public static function safe(string $value): string
    {
        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
