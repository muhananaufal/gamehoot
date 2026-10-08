<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\GameStatus;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\QuestionResult;
use Carbon\CarbonImmutable;

/**
 * D-11: played games with moments to replay, for tests. The event is a Pentahoot::event()
 * (Rita Wulandari, Budi Santoso, Ana Putri).
 */
final class Replay
{
    /**
     * A Pentahoot game whose first category was revealed (Rita 3 votes, Budi 1) and whose
     * second is still open, so only the first can come back.
     */
    public static function pentahoot(Event $event): Game
    {
        $game = Pentahoot::game($event, ['Most punctual?', 'Best presenter?']);
        $first = Pentahoot::question($game);
        $first->forceFill(['status' => QuestionStatus::Revealed])->save();

        foreach ([['Rita Wulandari', 1, 3], ['Budi Santoso', 2, 1]] as [$name, $rank, $votes]) {
            QuestionResult::query()->create([
                'question_id' => $first->id,
                'attempt' => 1,
                'person_id' => Pentahoot::person($event, $name)->id,
                'rank' => $rank,
                'votes' => $votes,
                'frozen_at' => now(),
            ]);
        }

        Pentahoot::question($game, 2)->forceFill(['status' => QuestionStatus::Live])->save();

        return $game;
    }

    /**
     * A finished Tebak Kata game: Rita won the first question, nobody the second (surrendered).
     */
    public static function kata(Event $event): Game
    {
        $game = TebakKata::game($event, [['Capital of France?', 'PARIS'], ['Largest ocean?', 'PACIFIC']], 'Word Round');
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        [$first, $second] = $game->questions()->orderBy('position')->get()->all();
        $first->forceFill(['status' => QuestionStatus::Won, 'winner_person_id' => $rita->id, 'resolved_at' => CarbonImmutable::parse('2026-10-07 10:00:00')])->save();
        $second->forceFill(['status' => QuestionStatus::Surrendered, 'resolved_at' => CarbonImmutable::parse('2026-10-07 10:01:00')])->save();
        $game->forceFill(['status' => GameStatus::Finished])->save();
        GameResult::query()->create([
            'game_id' => $game->id,
            'person_id' => $rita->id,
            'rank' => 1,
            'points' => 1,
            'reached_at' => CarbonImmutable::parse('2026-10-07 10:00:00'),
            'frozen_at' => now(),
        ]);

        return $game;
    }
}
