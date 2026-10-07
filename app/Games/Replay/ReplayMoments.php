<?php

declare(strict_types=1);

namespace App\Games\Replay;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Enums\ReplayKind;
use App\Games\Tebak\TebakBoard;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionResult;

/**
 * D-11: the past moments of an event that the Public View can replay, and what it draws for
 * the one being replayed. Only what was on screen comes back: revealed Pentahoot categories
 * (G10, the last attempt), won and surrendered Tebak questions, the leaderboard after each win
 * (E11) and the final podium of a finished Tebak game (E12). Read from the frozen results.
 *
 * @phpstan-type Row array{rank: int, name: string, score: int}
 * @phpstan-type Payload array{kind: string, type: string, game: string, number: ?int, title: string, prompt?: string, rows: list<Row>}
 */
final readonly class ReplayMoments
{
    /** E20: a Pentahoot ranking shows at most ten names. */
    private const int PENTAHOOT_ROWS = 10;

    public function __construct(private TebakBoard $board) {}

    /**
     * The games that have something to replay, in play order, each with its moments: the final
     * podium first, then each question in order, a Tebak leaderboard right after its question.
     *
     * @return list<array{game: Game, moments: list<Moment>}>
     */
    public function forEvent(Event $event): array
    {
        $groups = [];

        foreach ($event->games()->orderBy('position')->get() as $game) {
            $moments = $this->forGame($game);

            if ($moments !== []) {
                $groups[] = ['game' => $game, 'moments' => $moments];
            }
        }

        return $groups;
    }

    /**
     * The moment the marker points at, or null when it cannot be replayed.
     */
    public function find(Event $event, ReplayKind $kind, Game $game, ?Question $question = null): ?Moment
    {
        if ($game->event_id !== $event->id) {
            return null;
        }

        foreach ($this->forGame($game) as $moment) {
            if ($moment->is($kind, $game->id, $question?->id)) {
                return $moment;
            }
        }

        return null;
    }

    /**
     * What the Public View draws for the moment being replayed (F14: at most ten names).
     *
     * @return ?Payload
     */
    public function payload(Event $event): ?array
    {
        $kind = $event->replay_kind;
        $game = $event->replayGame()->first();
        $question = $event->replayQuestion()->first();

        if ($kind === null || $game === null || $this->find($event, $kind, $game, $question) === null) {
            return null;
        }

        $base = ['kind' => $kind->value, 'type' => $game->type->value, 'game' => $game->title];

        if ($kind === ReplayKind::Final || $question === null) {
            return [...$base, 'number' => null, 'title' => $game->title, 'rows' => self::scores($this->board->frozen($game))];
        }

        if ($kind === ReplayKind::Leaderboard) {
            return [...$base, 'number' => $question->position, 'title' => $this->answer($question), 'rows' => self::scores($this->board->after($game, $question))];
        }

        if ($game->type === GameType::Pentahoot) {
            return [...$base, 'number' => $question->position, 'title' => $this->answer($question), 'rows' => $this->ranking($question)];
        }

        $winner = $question->winner()->first();

        return [
            ...$base,
            'number' => $question->position,
            'title' => $this->answer($question),
            'prompt' => $this->prompt($question),
            'rows' => $winner === null ? [] : [['rank' => 1, 'name' => $winner->name, 'score' => $question->points]],
        ];
    }

    /**
     * @return list<Moment>
     */
    private function forGame(Game $game): array
    {
        $questions = $game->questions()->with(['pentahoot', 'kata', 'gambar', 'winner:id,name'])->orderBy('position')->get();

        if ($game->type === GameType::Pentahoot) {
            return array_values(array_filter($questions->map(function (Question $question) use ($game): ?Moment {
                $winners = array_map(fn (array $row): string => $row['name'], array_filter($this->ranking($question), fn (array $row): bool => $row['rank'] === 1));

                return $winners === [] ? null : new Moment(ReplayKind::Result, $game->id, $question->id, $question->position, $this->answer($question), array_values($winners));
            })->all()));
        }

        $moments = [];
        $final = $this->board->frozen($game);

        if ($final !== []) {
            $moments[] = new Moment(ReplayKind::Final, $game->id, null, null, $game->title, [$final[0]['name']]);
        }

        foreach ($questions as $question) {
            if (! in_array($question->status, [QuestionStatus::Won, QuestionStatus::Surrendered], true)) {
                continue;
            }

            $winner = $question->status === QuestionStatus::Won ? $question->winner : null;
            $names = $winner === null ? [] : [$winner->name];
            $moments[] = new Moment(ReplayKind::Result, $game->id, $question->id, $question->position, $this->answer($question), $names);

            if ($winner !== null) {
                $moments[] = new Moment(ReplayKind::Leaderboard, $game->id, $question->id, $question->position, $this->answer($question), $names);
            }
        }

        return $moments;
    }

    /**
     * G10: the frozen ranking of the last attempt of a revealed category.
     *
     * @return list<Row>
     */
    private function ranking(Question $question): array
    {
        $attempt = $question->pentahoot()->value('attempt');

        if (! in_array($question->status, [QuestionStatus::Revealed, QuestionStatus::Done], true) || ! is_numeric($attempt)) {
            return [];
        }

        return array_values($question->results()
            ->where('attempt', (int) $attempt)
            ->with('person:id,name')
            ->orderBy('rank')
            ->limit(self::PENTAHOOT_ROWS)
            ->get()
            ->map(fn (QuestionResult $row): array => ['rank' => $row->rank, 'name' => $row->person->name ?? '', 'score' => $row->votes])
            ->all());
    }

    /**
     * @param  list<array{rank: int, name: string, points: int, movement?: ?int}>  $rows
     * @return list<Row>
     */
    private static function scores(array $rows): array
    {
        return array_map(fn (array $row): array => ['rank' => $row['rank'], 'name' => $row['name'], 'score' => $row['points']], $rows);
    }

    /** The Pentahoot category, or the answer of a Tebak question. */
    private function answer(Question $question): string
    {
        return (string) ($question->pentahoot->prompt ?? $question->kata->answer_text ?? $question->gambar->answer_text ?? '');
    }

    /** The question of a Tebak question, as the screen asked it. */
    private function prompt(Question $question): string
    {
        return (string) ($question->kata->prompt ?? $question->gambar->title ?? '');
    }
}
