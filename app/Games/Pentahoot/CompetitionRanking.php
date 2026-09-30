<?php

declare(strict_types=1);

namespace App\Games\Pentahoot;

/**
 * E1: competition ranking (1-2-2-4). Everyone ranked 5 or better is shown, so ties can
 * make the list longer than five names; names without votes are never shown.
 *
 * @phpstan-type Tally array{person_id: string, name: string, votes: int}
 * @phpstan-type Ranked array{person_id: string, name: string, votes: int, rank: int}
 */
final class CompetitionRanking
{
    public const int LAST_SHOWN_RANK = 5;

    /**
     * @param  list<Tally>  $tally
     * @return list<Ranked>
     */
    public static function top(array $tally): array
    {
        $withVotes = array_values(array_filter($tally, fn (array $row): bool => $row['votes'] > 0));

        // Most votes first; ties alphabetically so every screen lists them the same way.
        usort($withVotes, fn (array $a, array $b): int => [$b['votes'], mb_strtolower($a['name'])] <=> [$a['votes'], mb_strtolower($b['name'])]);

        $ranked = [];
        $previousVotes = null;
        $rank = 0;

        foreach ($withVotes as $position => $row) {
            if ($row['votes'] !== $previousVotes) {
                $rank = $position + 1;
                $previousVotes = $row['votes'];
            }

            if ($rank > self::LAST_SHOWN_RANK) {
                break;
            }

            $ranked[] = [...$row, 'rank' => $rank];
        }

        return $ranked;
    }
}
