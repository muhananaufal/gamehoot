<?php

declare(strict_types=1);

namespace App\Results;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionResult;
use Carbon\CarbonImmutable;

/**
 * D-3, G10: the results of an event, read only from what is frozen, so a later change to the
 * scoring code never changes a past event.
 * - Pentahoot: the frozen top 5 of each question's current attempt (a Reset drops the
 *   earlier attempt, T1).
 * - Tebak Kata and Tebak Gambar: the winner of each question (final once picked, D-6) and the
 *   final board frozen when the game finished (E12). The answer is shown to hosts here.
 *
 * @phpstan-type Row array{rank: int, name: string, votes: int}
 * @phpstan-type QuestionResults array{number: int, prompt: string, played: bool, rows: list<Row>, answer: ?string, winner: ?string, points: ?int}
 * @phpstan-type BoardRow array{rank: int, name: string, points: int}
 * @phpstan-type GameResults array{title: string, type: GameType, questions: list<QuestionResults>, final: list<BoardRow>|null}
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
        $changes = [];

        $played = $event->games()
            ->orderBy('position')
            ->with(['questions.pentahoot', 'questions.kata', 'questions.gambar', 'questions.winner:id,name'])
            ->get();

        foreach ($played as $game) {
            $games[] = $game->type === GameType::Pentahoot
                ? self::pentahoot($game, $changes)
                : self::tebak($game, $changes);
        }

        $lastChange = null;

        foreach ($changes as $change) {
            $lastChange = $lastChange === null || $change->greaterThan($lastChange) ? $change : $lastChange;
        }

        return new self($games, $lastChange);
    }

    /**
     * @param  list<CarbonImmutable>  $changes
     * @return GameResults
     */
    private static function pentahoot(Game $game, array &$changes): array
    {
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
                $changes[] = $row->frozen_at;
            }

            $questions[] = [
                'number' => $question->position,
                'prompt' => $question->pentahoot->prompt ?? '',
                'played' => in_array($question->status, [QuestionStatus::Revealed, QuestionStatus::Done], true),
                'rows' => array_values($frozen->map(fn (QuestionResult $row): array => [
                    'rank' => $row->rank,
                    'name' => $row->person->name ?? '',
                    'votes' => $row->votes,
                ])->all()),
                'answer' => null,
                'winner' => null,
                'points' => null,
            ];
        }

        return ['title' => $game->title, 'type' => $game->type, 'questions' => $questions, 'final' => null];
    }

    /**
     * @param  list<CarbonImmutable>  $changes
     * @return GameResults
     */
    private static function tebak(Game $game, array &$changes): array
    {
        $questions = [];

        // UUIDv7 ids follow the pack order, which a Skip does not change.
        foreach ($game->questions->sortBy('id')->values() as $index => $question) {
            $resolved = in_array($question->status, [QuestionStatus::Won, QuestionStatus::Surrendered], true);

            if ($resolved && $question->resolved_at !== null) {
                $changes[] = $question->resolved_at;
            }

            $questions[] = [
                'number' => $index + 1,
                'prompt' => $question->kata->prompt ?? $question->gambar->title ?? '',
                'played' => $resolved,
                'rows' => [],
                'answer' => $resolved ? ($question->kata->answer_text ?? $question->gambar->answer_text ?? null) : null,
                'winner' => $question->status === QuestionStatus::Won ? ($question->winner->name ?? null) : null,
                'points' => $question->status === QuestionStatus::Won ? $question->points : null,
            ];
        }

        $final = null;
        $frozen = $game->results()->with('person:id,name')->orderBy('rank')->get();

        if ($frozen->isNotEmpty()) {
            $final = [];

            foreach ($frozen as $row) {
                $changes[] = $row->frozen_at;
                $final[] = ['rank' => $row->rank, 'name' => $row->person->name ?? '', 'points' => $row->points];
            }
        }

        return ['title' => $game->title, 'type' => $game->type, 'questions' => $questions, 'final' => $final];
    }
}
