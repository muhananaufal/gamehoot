<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-8: the host may end a game before every question is done. Unfinished questions do not
 * count; ending early is logged (E13). D-4: a finished game is not played again.
 */
final readonly class FinishGame
{
    public function __construct(private StatePublisher $publisher, private AuditLog $auditLog) {}

    /**
     * @throws ActionRefused
     */
    public function handle(Event $event, Game $game, User $actor): void
    {
        DB::transaction(function () use ($event, $game, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $lockedGame = Game::query()->lockForUpdate()->findOrFail($game->id);

            if ($locked->active_game_id !== $lockedGame->id) {
                throw ActionRefused::stale();
            }

            $early = $lockedGame->questions()->where('status', '!=', QuestionStatus::Done)->exists();

            $lockedGame->forceFill(['status' => GameStatus::Finished, 'current_question_id' => null])->save();
            $locked->active_game_id = null;
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);

            if ($early) {
                $this->auditLog->record(LoggedAction::GameFinishedEarly, $actor, $locked, ['game_id' => $lockedGame->id]);
            }
        });
    }
}
