<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;

/**
 * Builds Tebak Kata games for tests. Questions are [prompt, answer, initially open boxes, points].
 */
final class TebakKata
{
    /**
     * @param  list<array{0: string, 1: string, 2?: list<int>, 3?: int}>  $questions
     */
    public static function game(Event $event, array $questions = [['Capital of France?', 'PARIS', [0]], ['Largest ocean?', 'PACIFIC', [1]]], string $title = 'Word Guess'): Game
    {
        $last = $event->games()->max('position');
        $game = Game::factory()->for($event)->create([
            'type' => GameType::TebakKata,
            'title' => $title,
            'position' => is_numeric($last) ? (int) $last + 1 : 1,
        ]);

        foreach ($questions as $index => $question) {
            $copy = Question::factory()->for($game)->create([
                'position' => $index + 1,
                'points' => $question[3] ?? 1,
                'status' => QuestionStatus::Queued,
            ]);
            $open = $question[2] ?? [];
            $copy->kata()->forceCreate([
                'prompt' => $question[0],
                'answer_text' => $question[1],
                'initial_open_indexes' => $open,
                'opened_indexes' => $open,
            ]);
        }

        return $game;
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: list<int>, 3?: int}>  $questions
     */
    public static function running(Event $event, array $questions = [['Capital of France?', 'PARIS', [0]], ['Largest ocean?', 'PACIFIC', [1]]]): Game
    {
        $game = self::game($event, $questions);
        $event->forceFill(['active_game_id' => $game->id, 'names_locked_at' => now()])->save();

        return $game;
    }
}
