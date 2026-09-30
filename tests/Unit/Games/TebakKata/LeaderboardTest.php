<?php

declare(strict_types=1);

use App\Games\TebakKata\Leaderboard;
use Carbon\CarbonImmutable;

/**
 * @param  list<array{string, int, string}>  $wins  [name, points, resolved at]
 * @return list<array{person_id: string, name: string, points: int, resolved_at: CarbonImmutable}>
 */
function wins(array $wins): array
{
    return array_map(fn (array $win): array => [
        'person_id' => 'id-'.$win[0],
        'name' => $win[0],
        'points' => $win[1],
        'resolved_at' => CarbonImmutable::parse('2026-10-15 '.$win[2]),
    ], $wins);
}

/**
 * @param  list<array{rank: int, name: string, points: int, movement: ?int}>  $rows
 * @return list<string>
 */
function board(array $rows): array
{
    return array_map(fn (array $row): string => "{$row['rank']} {$row['name']} {$row['points']}", $rows);
}

describe('E10, E16 Tebak leaderboard', function (): void {
    it('adds the points of every question a person won', function (): void {
        expect(board(Leaderboard::top(wins([
            ['Rita', 1, '10:00:00'],
            ['Budi', 2, '10:01:00'],
            ['Rita', 1, '10:02:00'],
            ['Ana', 1, '10:03:00'],
        ]))))->toBe(['1 Budi 2', '2 Rita 2', '3 Ana 1']);
    });

    it('breaks ties by who reached the score first, so nobody shares a rank', function (): void {
        // Rita reached 2 at 10:02, Budi at 10:01: Budi ranks higher.
        expect(board(Leaderboard::top(wins([
            ['Rita', 1, '10:00:00'],
            ['Budi', 2, '10:01:00'],
            ['Rita', 1, '10:02:00'],
        ]))))->toBe(['1 Budi 2', '2 Rita 2']);
    });

    it('compares to the millisecond', function (): void {
        expect(board(Leaderboard::top(wins([
            ['Rita', 1, '10:00:00.002'],
            ['Budi', 1, '10:00:00.001'],
        ]))))->toBe(['1 Budi 1', '2 Rita 1']);
    });

    it('keeps exactly five names, or fewer when fewer people won', function (): void {
        $six = wins([['A', 1, '10:00'], ['B', 1, '10:01'], ['C', 1, '10:02'], ['D', 1, '10:03'], ['E', 1, '10:04'], ['F', 1, '10:05']]);

        expect(Leaderboard::top($six))->toHaveCount(5)
            ->and(Leaderboard::top([]))->toBe([]);
    });

    it('counts a win worth 0 points as a winner with no points (E16)', function (): void {
        expect(board(Leaderboard::top(wins([['Rita', 0, '10:00'], ['Budi', 1, '10:01']]))))->toBe(['1 Budi 1', '2 Rita 0']);
    });

    it('says how far each name moved since the board before the last win (E11)', function (): void {
        $rows = Leaderboard::top(wins([
            ['Rita', 1, '10:00:00'],
            ['Budi', 1, '10:01:00'],
            ['Budi', 1, '10:02:00'],
            ['Ana', 1, '10:03:00'],
        ]));

        expect(array_map(fn (array $row): string => $row['name'].' '.var_export($row['movement'], true), $rows))
            ->toBe(['Budi 0', 'Rita 0', 'Ana NULL']);

        $overtake = Leaderboard::top(wins([['Rita', 1, '10:00'], ['Budi', 2, '10:01']]));
        expect(array_map(fn (array $row): string => $row['name'].' '.var_export($row['movement'], true), $overtake))
            ->toBe(['Budi NULL', 'Rita -1']);

        $climb = Leaderboard::top(wins([['Rita', 2, '10:00'], ['Budi', 1, '10:01'], ['Budi', 1, '10:02']]));
        expect(array_map(fn (array $row): string => $row['name'].' '.var_export($row['movement'], true), $climb))
            ->toBe(['Rita 0', 'Budi 0']);

        $pass = Leaderboard::top(wins([['Rita', 1, '10:00'], ['Budi', 1, '10:01'], ['Budi', 1, '10:02']]));
        expect(array_map(fn (array $row): string => $row['name'].' '.var_export($row['movement'], true), $pass))
            ->toBe(['Budi 1', 'Rita -1']);
    });
});
