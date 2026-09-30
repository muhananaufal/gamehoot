<?php

declare(strict_types=1);

namespace App\Games\TebakKata;

use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use App\Models\Question;
use App\Models\QuestionKata;
use App\Models\User;
use App\Support\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The host actions of a Tebak Kata question: queued -> shown -> won | surrendered, with one
 * Skip back to the end of the queue (spec section 05). G12: the game and question rows are
 * locked; anything not allowed in the current state is STALE_ACTION (C-3). The caller
 * (RunQuestionAction) holds the transaction and the event lock and publishes.
 */
final readonly class KataActions
{
    public const array ACTIONS = ['show', 'hint', 'skip', 'winner', 'surrender', 'leaderboard'];

    public function __construct(private AuditLog $auditLog) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $action): array
    {
        return match ($action) {
            'hint' => ['box' => ['required', 'integer', 'min:0']],
            'winner' => ['person' => ['required', 'uuid']],
            default => [],
        };
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ActionRefused
     */
    public function perform(string $action, Event $event, Question $question, User $actor, array $input): void
    {
        $game = Game::query()->lockForUpdate()->findOrFail($question->game_id);
        $locked = Question::query()->lockForUpdate()->findOrFail($question->id);
        $detail = QuestionKata::query()->lockForUpdate()->findOrFail($question->id);

        if ($event->active_game_id !== $game->id || $game->status === GameStatus::Finished) {
            throw ActionRefused::stale();
        }

        match ($action) {
            'show' => $this->show($game, $locked),
            'hint' => $this->hint($game, $locked, $detail, is_numeric($input['box'] ?? null) ? (int) $input['box'] : -1),
            'skip' => $this->skip($game, $locked),
            'winner' => $this->winner($event, $game, $locked, $actor, is_string($input['person'] ?? null) ? $input['person'] : ''),
            'surrender' => $this->surrender($event, $game, $locked, $actor),
            'leaderboard' => $this->leaderboard($game, $locked),
            default => throw ActionRefused::stale(),
        };
    }

    /**
     * D-5: any queued question can be shown, while no other question is on screen (G7).
     * E6: a skipped question can be pulled back, with its Skip already used.
     */
    private function show(Game $game, Question $question): void
    {
        if ($question->status !== QuestionStatus::Queued) {
            throw ActionRefused::stale();
        }

        if ($game->current_question_id !== null && $game->current_question_id !== $question->id) {
            $current = Question::query()->findOrFail($game->current_question_id);

            if ($current->status === QuestionStatus::Shown) {
                throw ActionRefused::stale();
            }
        }

        $question->forceFill(['status' => QuestionStatus::Shown])->save();
        $game->forceFill(['current_question_id' => $question->id, 'leaderboard_at' => null])->save();
    }

    /**
     * E5: a hint opens one closed box; the last closed box stays closed, like at the start.
     */
    private function hint(Game $game, Question $question, QuestionKata $detail, int $box): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Shown);

        $count = AnswerBoxes::fromAnswer($detail->answer_text)->count;
        $opened = $detail->opened_indexes;

        if ($box < 0 || $box >= $count || in_array($box, $opened, true) || count($opened) + 1 >= $count) {
            throw ActionRefused::stale();
        }

        $opened[] = $box;
        sort($opened);
        $detail->forceFill(['opened_indexes' => $opened])->save();
    }

    /**
     * Once per question, to the end of the queue. E6: not when nothing else is queued.
     */
    private function skip(Game $game, Question $question): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Shown);

        $othersQueued = $game->questions()->where('status', QuestionStatus::Queued)->whereKeyNot($question->id)->exists();

        if ($question->skip_used || ! $othersQueued) {
            throw ActionRefused::stale();
        }

        $last = $game->questions()->max('position');
        $question->forceFill([
            'status' => QuestionStatus::Queued,
            'skip_used' => true,
            'position' => (is_numeric($last) ? (int) $last : 0) + 1,
        ])->save();
        $game->forceFill(['current_question_id' => null])->save();
    }

    /**
     * D-6 (the confirmation is on the host page), E13: a winner is final and logged. The
     * question stays on screen with the answer and the winner.
     */
    private function winner(Event $event, Game $game, Question $question, User $actor, string $personId): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Shown);

        $person = Person::query()->whereKey($personId)->where('event_id', $event->id)->first();

        if ($person === null) {
            throw ValidationException::withMessages(['person' => __('games.kata.winner_unknown')]);
        }

        $question->winner()->associate($person);
        $question->forceFill(['status' => QuestionStatus::Won, 'resolved_at' => CarbonImmutable::now()])->save();

        $this->auditLog->record(LoggedAction::WinnerPicked, $actor, $event, ['question_id' => $question->id, 'person_id' => $person->id]);
    }

    /**
     * E13: no winner, the answer is shown; logged.
     */
    private function surrender(Event $event, Game $game, Question $question, User $actor): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Shown);

        $question->forceFill(['status' => QuestionStatus::Surrendered, 'resolved_at' => CarbonImmutable::now()])->save();

        $this->auditLog->record(LoggedAction::QuestionSurrendered, $actor, $event, ['question_id' => $question->id]);
    }

    /**
     * E11: after a win the host may show the leaderboard; it stays until the next question (G14).
     */
    private function leaderboard(Game $game, Question $question): void
    {
        $this->expectCurrent($game, $question, QuestionStatus::Won);

        $game->forceFill(['leaderboard_at' => CarbonImmutable::now()])->save();
    }

    private function expectCurrent(Game $game, Question $question, QuestionStatus $status): void
    {
        if ($game->current_question_id !== $question->id || $question->status !== $status) {
            throw ActionRefused::stale();
        }
    }
}
