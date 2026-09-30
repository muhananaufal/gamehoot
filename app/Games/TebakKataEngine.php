<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Audience;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Games\Tebak\TebakBoard;
use App\Games\TebakKata\AnswerBoxes;
use App\Games\TebakKata\KataActions;
use App\Models\Event;
use App\Models\Game;
use App\Models\PackQuestion;
use App\Models\Question;
use App\Models\QuestionKata;
use App\Models\User;
use App\Rules\KataAnswer;
use App\Rules\KataOpenIndexes;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final readonly class TebakKataEngine implements LiveGameEngine
{
    public function __construct(private KataActions $actions, private TebakBoard $board) {}

    public function type(): GameType
    {
        return GameType::TebakKata;
    }

    public function imageFields(): array
    {
        return [];
    }

    public function questionRules(bool $creating): array
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
        $resolved = $questions->filter(fn (Question $question): bool => TebakBoard::resolved($question))->count();

        $snapshot = [
            'id' => $game->id,
            'type' => $game->type->value,
            'title' => $game->title,
            'status' => $game->status->value,
            'count' => $questions->count(),
            'question' => $current === null ? null : $this->questionPart($current, $audience, $resolved),
            'leaderboard' => $game->leaderboard_at === null ? null : $this->board->live($game),
            'final' => $game->status === GameStatus::Finished ? $this->board->frozen($game) : null,
        ];

        if ($audience === Audience::Host) {
            $snapshot['statuses'] = TebakBoard::statusCodes($questions);
            $snapshot['live_board'] = $this->board->live($game);
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
        $this->board->freeze($game, $at);
    }

    public function liveQuestions(Game $game): Collection
    {
        // UUIDv7 ids follow the order the questions were copied in, which a Skip does not change.
        return $game->questions()->orderBy('id')->with('kata')->get();
    }

    public function picksWinners(): bool
    {
        return true;
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

        $resolved = TebakBoard::resolved($question);
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
}
