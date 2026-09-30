<?php

declare(strict_types=1);

namespace App\Results;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Question;
use App\Models\QuestionResult;
use Carbon\CarbonImmutable;

/**
 * D-3, G10: the results of an event, read only from the frozen tables so a later change to
 * the scoring code never changes a past event. Pentahoot: the frozen top 5 of each
 * question's current attempt (a Reset drops the earlier attempt, T1).
 *
 * @phpstan-type Row array{rank: int, name: string, votes: int}
 * @phpstan-type QuestionResults array{number: int, prompt: string, played: bool, rows: list<Row>}
 * @phpstan-type GameResults array{title: string, type: GameType, questions: list<QuestionResults>}
 */
final class EventResults
{
    /**
     * @param  list<GameResults>  $games
     */
    private function __construct(public readonly array $games, public readonly ?CarbonImmutable $lastChange) {}

    public static function for(Event $event): self
    {
        $games = [];
        $lastChange = null;

        $played = $event->games()->where('type', GameType::Pentahoot)->orderBy('position')->with('questions.pentahoot')->get();

        foreach ($played as $game) {
            $questions = [];

            foreach ($game->questions->sortBy('position') as $question) {
                $attempt = $question->pentahoot->attempt ?? 1;
                $frozen = QuestionResult::query()
                    ->where('question_id', $question->id)
                    ->where('attempt', $attempt)
                    ->with('person:id,name')
                    ->get()
                    ->sortBy(fn (QuestionResult $row): string => sprintf('%05d %s', $row->rank, mb_strtolower($row->person->name ?? '')));

                foreach ($frozen as $row) {
                    $lastChange = $lastChange === null || $row->frozen_at->greaterThan($lastChange) ? $row->frozen_at : $lastChange;
                }

                $questions[] = [
                    'number' => $question->position,
                    'prompt' => $question->pentahoot->prompt ?? '',
                    'played' => self::revealed($question),
                    'rows' => array_values($frozen->map(fn (QuestionResult $row): array => [
                        'rank' => $row->rank,
                        'name' => $row->person->name ?? '',
                        'votes' => $row->votes,
                    ])->all()),
                ];
            }

            $games[] = ['title' => $game->title, 'type' => $game->type, 'questions' => $questions];
        }

        return new self($games, $lastChange);
    }

    private static function revealed(Question $question): bool
    {
        return in_array($question->status, [QuestionStatus::Revealed, QuestionStatus::Done], true);
    }
}
