<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Exported CSV files open in Excel: a cell starting with = + - @ would run as a formula
 * (CSV injection), so it gets a leading apostrophe.
 */
final class CsvCell
{
    public static function safe(string $value): string
    {
        return in_array(substr($value, 0, 1), ['=', '+', '-', '@'], true) ? "'".$value : $value;
    }
}
