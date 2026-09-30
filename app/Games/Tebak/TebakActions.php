<?php

declare(strict_types=1);

namespace App\Games\Tebak;

use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use App\Models\Question;
use App\Models\User;
use App\Support\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * The host actions Tebak Kata and Tebak Gambar share (F13): queued -> shown -> won | surrendered,
 * with one Skip back to the end of the queue (spec section 05). Each game adds its own action
 * (hint, Reveal) around these. G12: the game and question rows are locked; anything not allowed
 * in the current state is STALE_ACTION (C-3). The caller (RunQuestionAction) holds the
 * transaction and the event lock and publishes.
 */
final readonly class TebakActions
{
    public const array ACTIONS = ['show', 'skip', 'winner', 'surrender', 'leaderboard'];

    public function __construct(private AuditLog $auditLog) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $action): array
    {
        return match ($action) {
            'winner' => ['person' => ['required', 'uuid']],
            default => [],
        };
    }

    /**
     * Locks the game and the question; only the active game, while it is not finished (G7).
     *
     * @return array{Game, Question}
     *
     * @throws ActionRefused
     */
    public function lock(Event $event, Question $question): array
    {
        $game = Game::query()->lockForUpdate()->findOrFail($question->game_id);
        $locked = Question::query()->lockForUpdate()->findOrFail($question->id);

        if ($event->active_game_id !== $game->id || $game->status === GameStatus::Finished) {
            throw ActionRefused::stale();
        }

        return [$game, $locked];
    }

    /**
     * Runs one of ACTIONS on rows locked by lock().
     *
     * @param  array<string, mixed>  $input
     *
     * @throws ActionRefused
     */
    public function perform(string $action, Event $event, Game $game, Question $question, User $actor, array $input): void
    {
        match ($action) {
            'show' => $this->show($game, $question),
            'skip' => $this->skip($game, $question),
            'winner' => $this->winner($event, $game, $question, $actor, is_string($input['person'] ?? null) ? $input['person'] : ''),
            'surrender' => $this->surrender($event, $game, $question, $actor),
            'leaderboard' => $this->leaderboard($game, $question),
            default => throw ActionRefused::stale(),
        };
    }

    /**
     * @throws ActionRefused
     */
    public function expectCurrent(Game $game, Question $question, QuestionStatus $status): void
    {
        if ($game->current_question_id !== $question->id || $question->status !== $status) {
            throw ActionRefused::stale();
        }
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
            throw ValidationException::withMessages(['person' => __('games.tebak.winner_unknown')]);
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
}
