<?php

declare(strict_types=1);

namespace App\Games\Tebak;

use Carbon\CarbonImmutable;

/**
 * E10, E16, G1: points per game are the sum of the points of the questions a person won.
 * Ties go to whoever reached their score first (the time of their last win), so nobody
 * shares a rank: exactly five names, or fewer when fewer people won. Deliberately unlike
 * the Pentahoot ranking (E1).
 *
 * @phpstan-type Win array{person_id: string, name: string, points: int, resolved_at: CarbonImmutable}
 * @phpstan-type Row array{person_id: string, name: string, points: int, reached_at: CarbonImmutable, rank: int, movement: ?int}
 */
final class Leaderboard
{
    public const int SIZE = 5;

    /**
     * @param  list<Win>  $wins
     * @return list<Row> ranked, with the change in rank since the board before the last win (E11): positive moved up, null is new
     */
    public static function top(array $wins): array
    {
        $current = self::rank($wins);

        if ($wins === []) {
            return [];
        }

        $previous = [];

        foreach (self::rank(self::withoutLatest($wins), PHP_INT_MAX) as $row) {
            $previous[$row['person_id']] = $row['rank'];
        }

        return array_map(fn (array $row): array => [
            ...$row,
            'movement' => isset($previous[$row['person_id']]) ? $previous[$row['person_id']] - $row['rank'] : null,
        ], $current);
    }

    /**
     * @param  list<Win>  $wins
     * @return list<array{person_id: string, name: string, points: int, reached_at: CarbonImmutable, rank: int}>
     */
    private static function rank(array $wins, int $size = self::SIZE): array
    {
        $scores = [];

        foreach ($wins as $win) {
            $id = $win['person_id'];
            $scores[$id] = [
                'person_id' => $id,
                'name' => $win['name'],
                'points' => ($scores[$id]['points'] ?? 0) + $win['points'],
                // The last win is when the person reached their current score.
                'reached_at' => isset($scores[$id]) && $scores[$id]['reached_at']->greaterThan($win['resolved_at'])
                    ? $scores[$id]['reached_at']
                    : $win['resolved_at'],
            ];
        }

        $rows = array_values($scores);
        usort($rows, fn (array $a, array $b): int => [$b['points'], $a['reached_at']->getTimestampMs()] <=> [$a['points'], $b['reached_at']->getTimestampMs()]);

        $ranked = [];

        foreach (array_slice($rows, 0, $size) as $index => $row) {
            $ranked[] = [...$row, 'rank' => $index + 1];
        }

        return $ranked;
    }

    /**
     * @param  list<Win>  $wins
     * @return list<Win>
     */
    private static function withoutLatest(array $wins): array
    {
        $latest = 0;

        foreach ($wins as $index => $win) {
            if ($win['resolved_at']->greaterThanOrEqualTo($wins[$latest]['resolved_at'])) {
                $latest = $index;
            }
        }

        unset($wins[$latest]);

        return array_values($wins);
    }
}
