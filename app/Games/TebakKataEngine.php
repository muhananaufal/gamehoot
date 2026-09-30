<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Audience;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Games\TebakKata\AnswerBoxes;
use App\Games\TebakKata\KataActions;
use App\Games\TebakKata\Leaderboard;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\PackQuestion;
use App\Models\Question;
use App\Models\QuestionKata;
use App\Models\User;
use App\Rules\KataAnswer;
use App\Rules\KataOpenIndexes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * @phpstan-import-type Win from Leaderboard
 */
final readonly class TebakKataEngine implements LiveGameEngine
{
    public function __construct(private KataActions $actions) {}

    public function type(): GameType
    {
        return GameType::TebakKata;
    }

    public function questionRules(): array
    {
        return [
            'prompt' => ['required', 'string', 'max:500'],
            // T9, E5
            'answer_text' => ['required', 'string', 'max:40', new KataAnswer],
            // Absent when no box is opened: the form sends no inputs for an empty selection.
            'initial_open_indexes' => ['nullable', 'array', new KataOpenIndexes('answer_text')],
            'initial_open_indexes.*' => ['integer', 'distinct'],
            // E16
            'points' => ['required', 'integer', 'between:0,2'],
        ];
    }

    public function detailAttributes(array $validated): array
    {
        $attributes = Arr::only($validated, ['prompt', 'answer_text']);
        $attributes['initial_open_indexes'] = $validated['initial_open_indexes'] ?? [];

        if (isset($attributes['answer_text']) && is_string($attributes['answer_text'])) {
            $attributes['answer_text'] = trim($attributes['answer_text']);
        }

        if (is_array($attributes['initial_open_indexes'])) {
            // Form input arrives as numeric strings; questionRules() already rejected anything else.
            $indexes = [];
            foreach ($attributes['initial_open_indexes'] as $index) {
                if (is_numeric($index)) {
                    $indexes[] = (int) $index;
                }
            }
            sort($indexes);
            $attributes['initial_open_indexes'] = array_values(array_unique($indexes));
        }

        return $attributes;
    }

    public function questionAttributes(array $validated): array
    {
        return Arr::only($validated, ['points']);
    }

    public function savePackDetail(PackQuestion $question, array $attributes): void
    {
        $question->kata()->updateOrCreate([], $attributes);
    }

    public function copyDetail(PackQuestion $source, Question $copy): void
    {
        $detail = $source->kata()->firstOrFail();

        $copy->kata()->forceCreate([
            'prompt' => $detail->prompt,
            'answer_text' => $detail->answer_text,
            'initial_open_indexes' => $detail->initial_open_indexes,
            'opened_indexes' => $detail->initial_open_indexes,
        ]);
    }

    /**
     * The boxes open at the start of the edited question are its open boxes again (E5).
     */
    public function saveCopyDetail(Question $question, array $attributes): void
    {
        $detail = $question->kata()->firstOrFail();
        $detail->fill($attributes);
        $detail->opened_indexes = $detail->initial_open_indexes;
        $detail->save();
    }

    public function initialStatus(): QuestionStatus
    {
        return QuestionStatus::Queued;
    }

    /**
     * F1, F2, E5: the question on screen. Closed boxes never carry their letter on the public
     * channel; the answer and the winner arrive once the question is resolved. E11: the
     * leaderboard while shown (G14); E12, E17: the frozen final board once the game finished.
     */
    public function snapshot(Game $game, Audience $audience): array
    {
        // UUIDv7 ids follow the order the questions were copied in, which a Skip does not change.
        $questions = $game->questions()->orderBy('id')->get(['id', 'position', 'status', 'skip_used']);
        $current = $game->currentQuestion()->with(['kata', 'winner:id,name'])->first();
        $resolved = $questions->filter(fn (Question $question): bool => $this->resolved($question))->count();

        $snapshot = [
            'id' => $game->id,
            'type' => $game->type->value,
            'title' => $game->title,
            'status' => $game->status->value,
            'count' => $questions->count(),
            'question' => $current === null ? null : $this->questionPart($current, $audience, $resolved),
            'leaderboard' => $game->leaderboard_at === null ? null : $this->board($game),
            'final' => $game->status === GameStatus::Finished ? $this->frozenBoard($game) : null,
        ];

        if ($audience === Audience::Host) {
            // F14: one letter per question, in copy order; the prompts are on the host page already.
            // q queued, s shown, w won, x surrendered; a capital Q is queued after a Skip.
            $snapshot['statuses'] = $questions->map(fn (Question $question): string => match ($question->status) {
                QuestionStatus::Shown => 's',
                QuestionStatus::Won => 'w',
                QuestionStatus::Surrendered => 'x',
                default => $question->skip_used ? 'Q' : 'q',
            })->values()->all();
            $snapshot['live_board'] = $this->board($game);
        }

        return $snapshot;
    }

    public function liveCounters(Game $game, Audience $audience): array
    {
        return [];
    }

    public function actions(): array
    {
        return KataActions::ACTIONS;
    }

    public function actionRules(string $action): array
    {
        return KataActions::rules($action);
    }

    public function perform(string $action, Event $event, Question $question, User $actor, array $input): void
    {
        $this->actions->perform($action, $event, $question, $actor, $input);
    }

    /**
     * G10, E10, E12: the final top 5 is frozen when the game finishes.
     */
    public function finish(Game $game, CarbonImmutable $at): void
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
     * D-2: once shown (or skipped after being shown) a question is locked.
     */
    public function wasShown(Question $question): bool
    {
        return $question->status !== QuestionStatus::Queued || $question->skip_used;
    }

    /**
     * @return array<string, mixed>
     */
    private function questionPart(Question $question, Audience $audience, int $resolvedCount): array
    {
        $detail = $question->kata;

        if (! $detail instanceof QuestionKata) {
            return [];
        }

        $resolved = $this->resolved($question);
        $initial = $detail->initial_open_indexes;
        $opened = $detail->opened_indexes;
        $words = [];

        foreach (AnswerBoxes::fromAnswer($detail->answer_text)->words as $word) {
            $cells = [];

            foreach ($word as $cell) {
                $isOpen = $cell->box === null || $resolved || in_array($cell->box, $opened, true);
                // F14: short keys: c letter (null while closed), b box number (null for punctuation),
                // o how it opened: i at the start, h by a hint.
                $cells[] = [
                    'c' => $isOpen ? $cell->char : null,
                    'b' => $cell->box,
                    'o' => $cell->box === null ? null : (in_array($cell->box, $initial, true) ? 'i' : (in_array($cell->box, $opened, true) ? 'h' : null)),
                ];
            }

            $words[] = $cells;
        }

        $part = [
            'id' => $question->id,
            'number' => $resolved ? $resolvedCount : $resolvedCount + 1,
            'prompt' => $detail->prompt,
            'status' => $question->status->value,
            'points' => $question->points,
            'skipped' => $question->skip_used,
            'boxes' => $words,
            'winner' => $question->winner->name ?? null,
            'answer' => $resolved ? $detail->answer_text : null,
        ];

        if ($audience === Audience::Host) {
            // Only hosts see the answer while the question is open.
            $part['answer'] = $detail->answer_text;
        }

        return $part;
    }

    private function resolved(Question $question): bool
    {
        return in_array($question->status, [QuestionStatus::Won, QuestionStatus::Surrendered], true);
    }

    /**
     * G1: the live board, computed from the data while the game runs.
     *
     * @return list<array{rank: int, name: string, points: int, movement: ?int}>
     */
    private function board(Game $game): array
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
    private function frozenBoard(Game $game): array
    {
        return array_values($game->results()->with('person:id,name')->orderBy('rank')->get()
            ->map(fn (GameResult $row): array => ['rank' => $row->rank, 'name' => $row->person->name ?? '', 'points' => $row->points])
            ->all());
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
