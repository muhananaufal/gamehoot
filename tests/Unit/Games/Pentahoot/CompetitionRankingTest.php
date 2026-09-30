<?php

declare(strict_types=1);

use App\Games\Pentahoot\CompetitionRanking;

/**
 * @param  array<string, int>  $votes  name => votes
 * @return list<string> "rank name votes" rows
 */
function ranked(array $votes): array
{
    $tally = [];

    foreach ($votes as $name => $count) {
        $tally[] = ['person_id' => 'id-'.$name, 'name' => (string) $name, 'votes' => $count];
    }

    return array_map(
        fn (array $row): string => "{$row['rank']} {$row['name']} {$row['votes']}",
        CompetitionRanking::top($tally),
    );
}

describe('E1 competition ranking', function (): void {
    it('shares a rank between ties and skips the ranks they use (1-2-2-4)', function (): void {
        expect(ranked(['A' => 10, 'B' => 8, 'C' => 8, 'D' => 5, 'E' => 5, 'F' => 5, 'G' => 3]))->toBe([
            '1 A 10',
            '2 B 8',
            '2 C 8',
            '4 D 5',
            '4 E 5',
            '4 F 5',
        ]);
    });

    it('shows everyone ranked 5 or better, so ties can push the list past five names', function (): void {
        expect(ranked(['A' => 9, 'B' => 7, 'C' => 6, 'D' => 4, 'E' => 2, 'F' => 2, 'G' => 2, 'H' => 1]))->toBe([
            '1 A 9',
            '2 B 7',
            '3 C 6',
            '4 D 4',
            '5 E 2',
            '5 F 2',
            '5 G 2',
        ]);
    });

    it('never shows names without votes', function (): void {
        expect(ranked(['A' => 2, 'B' => 0, 'C' => 1]))->toBe(['1 A 2', '2 C 1'])
            ->and(ranked(['A' => 0]))->toBe([])
            ->and(ranked([]))->toBe([]);
    });

    it('lists tied names alphabetically, whatever order the votes came in', function (): void {
        expect(ranked(['rita' => 3, 'Budi' => 3, 'ana' => 3]))->toBe(['1 ana 3', '1 Budi 3', '1 rita 3']);
    });

    it('drops the whole tie at rank 6 instead of cutting it', function (): void {
        expect(ranked(['A' => 6, 'B' => 5, 'C' => 4, 'D' => 3, 'E' => 2, 'F' => 1, 'G' => 1]))->toHaveCount(5);
    });
});
