<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\GameStatus;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;

/**
 * Audit PERF-3, CODE-4: what is on screen right now, read once per snapshot and shared by the
 * engine counters (F24), the phone's own state (E4) and the replay guard (D-11), instead of
 * each of them reading the active game and question again. A value, never kept between
 * calls, so an action that changes the screen is always seen by the snapshot after it.
 */
final readonly class LiveState
{
    public function __construct(public ?Game $game, public ?Question $question) {}

    public static function of(Event $event): self
    {
        $game = $event->trashed() ? null : $event->activeGame()->first();

        if (! $game instanceof Game || $game->current_question_id === null) {
            return new self($game instanceof Game ? $game : null, null);
        }

        $question = Question::query()->with('pentahoot')->find($game->current_question_id);

        return new self($game, $question instanceof Question ? $question : null);
    }

    /**
     * D-11: a question is on screen while it is live or shown in a game that still runs.
     */
    public function questionOnScreen(): bool
    {
        return $this->game instanceof Game
            && $this->game->status !== GameStatus::Finished
            && in_array($this->question?->status, [QuestionStatus::Live, QuestionStatus::Shown], true);
    }
}
