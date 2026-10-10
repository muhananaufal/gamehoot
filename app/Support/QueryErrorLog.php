<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\QueryException;

/**
 * K5 (audit SEC-9): what a failed query writes to the log. The SQL comes without its values
 * (the connection masks bindings), and the driver message loses the quoted values it repeats,
 * such as "Duplicate entry '<event>-<name>'". Names of keys, columns and tables stay, so the
 * line still tells which rule refused the query.
 */
final class QueryErrorLog
{
    private const int TRACE_FRAMES = 10;

    /**
     * @return array{error: string, sql: string, sqlstate: string, trace: list<string>}
     */
    public static function context(QueryException $exception): array
    {
        $driverMessage = $exception->getPrevious()?->getMessage() ?? $exception->getMessage();

        return [
            'error' => self::maskValues($driverMessage),
            'sql' => $exception->getSql(),
            'sqlstate' => (string) $exception->getCode(),
            'trace' => self::trace($exception),
        ];
    }

    /**
     * Replaces every quoted value with '?', except the names that follow "key", "column",
     * "table", "index" or "database".
     */
    public static function maskValues(string $message): string
    {
        return preg_replace_callback(
            "/(\\b(?:key|column|table|index|database)\\s+)?'(?:[^'\\\\]|\\\\.)*'/i",
            static fn (array $match): string => ($match[1] ?? '') !== '' ? $match[0] : "'?'",
            $message,
        ) ?? '';
    }

    /**
     * File and line only: frames never carry their arguments.
     *
     * @return list<string>
     */
    private static function trace(QueryException $exception): array
    {
        $frames = [];

        foreach (array_slice($exception->getTrace(), 0, self::TRACE_FRAMES) as $frame) {
            if (isset($frame['file'], $frame['line'])) {
                $frames[] = $frame['file'].':'.$frame['line'];
            }
        }

        return $frames;
    }
}
