<?php

declare(strict_types=1);

namespace App\Games\Tebak;

use App\Enums\QuestionStatus;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Question;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

/**
 * The board Tebak Kata and Tebak Gambar share (F13): the live top 5 from the wins (E10, E11),
 * the final top 5 frozen when the game finishes (G10, E12) and the status letters of the host
 * snapshot (F14).
 *
 * @phpstan-import-type Win from Leaderboard
 */
final readonly class TebakBoard
{
    public static function resolved(Question $question): bool
    {
        return in_array($question->status, [QuestionStatus::Won, QuestionStatus::Surrendered], true);
    }

    /**
     * F14: one letter per question, in copy order; the prompts are on the host page already.
     * q queued, s shown, w won, x surrendered; a capital Q is queued after a Skip.
     *
     * @param  Collection<int, Question>  $questions
     * @return list<string>
     */
    public static function statusCodes(Collection $questions): array
    {
        return array_values($questions->map(fn (Question $question): string => match ($question->status) {
            QuestionStatus::Shown => 's',
            QuestionStatus::Won => 'w',
            QuestionStatus::Surrendered => 'x',
            default => $question->skip_used ? 'Q' : 'q',
        })->all());
    }

    /**
     * G1: the live board, computed from the data while the game runs.
     *
     * @return list<array{rank: int, name: string, points: int, movement: ?int}>
     */
    public function live(Game $game): array
    {
        return array_map(
            fn (array $row): array => ['rank' => $row['rank'], 'name' => $row['name'], 'points' => $row['points'], 'movement' => $row['movement']],
            Leaderboard::top($this->wins($game)),
        );
    }

    /**
     * G10: the final board, read from the frozen table.
     *
     * @return list<array{rank: int, name: string, points: int}>
     */
    public function frozen(Game $game): array
    {
        return array_values($game->results()->with('person:id,name')->orderBy('rank')->get()
            ->map(fn (GameResult $row): array => ['rank' => $row->rank, 'name' => $row->person->name ?? '', 'points' => $row->points])
            ->all());
    }

    /**
     * G10, E10, E12: the final top 5 is frozen when the game finishes.
     */
    public function freeze(Game $game, CarbonImmutable $at): void
    {
        foreach (Leaderboard::top($this->wins($game)) as $row) {
            GameResult::query()->create([
                'game_id' => $game->id,
                'person_id' => $row['person_id'],
                'rank' => $row['rank'],
                'points' => $row['points'],
                'reached_at' => $row['reached_at'],
                'frozen_at' => $at,
            ]);
        }
    }

    /**
     * @return list<Win>
     */
    private function wins(Game $game): array
    {
        return array_values($game->questions()
            ->where('status', QuestionStatus::Won)
            ->whereNotNull('winner_person_id')
            ->with('winner:id,name')
            ->get()
            ->map(fn (Question $question): array => [
                'person_id' => (string) $question->winner_person_id,
                'name' => $question->winner->name ?? '',
                'points' => $question->points,
                'resolved_at' => $question->resolved_at ?? CarbonImmutable::now(),
            ])
            ->all());
    }
}
