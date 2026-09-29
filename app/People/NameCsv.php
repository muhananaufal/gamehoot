<?php

declare(strict_types=1);

namespace App\People;

/**
 * T4: names exported from Excel. The separator (, or ;) is detected from the first row,
 * the UTF-8 BOM is dropped, and only the first column is read.
 */
final readonly class NameCsv
{
    /** A first row with one of these in the first column is a header, not a name. */
    private const array HEADERS = ['name', 'nama'];

    /**
     * @param  list<string>  $names
     */
    private function __construct(
        public array $names,
        public string $separator,
    ) {}

    public static function parse(string $contents): self
    {
        $contents = preg_replace('/^\x{FEFF}/u', '', $contents) ?? $contents;
        $lines = preg_split('/\r\n|\n|\r/', $contents) ?: [];
        $separator = self::detectSeparator($lines);

        $names = [];

        foreach ($lines as $index => $line) {
            if (trim($line) === '') {
                continue;
            }

            $name = PersonName::display((string) (str_getcsv($line, $separator, '"', '')[0] ?? ''));

            if ($name === '' || ($index === 0 && in_array(mb_strtolower($name), self::HEADERS, true))) {
                continue;
            }

            $names[] = $name;
        }

        return new self($names, $separator);
    }

    /**
     * @param  array<int, string>  $lines
     */
    private static function detectSeparator(array $lines): string
    {
        foreach ($lines as $line) {
            if (trim($line) !== '') {
                // Separators inside quoted values do not count.
                $unquoted = preg_replace('/"[^"]*"/', '', $line) ?? $line;

                return substr_count($unquoted, ';') > substr_count($unquoted, ',') ? ';' : ',';
            }
        }

        return ',';
    }
}
