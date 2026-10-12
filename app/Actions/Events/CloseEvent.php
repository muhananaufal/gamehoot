<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Actions\Games\FinishGame;
use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use App\Support\SeenVersion;
use Illuminate\Support\Facades\DB;

/**
 * D-7: players see "This event has ended". T6, D-10: a game still running is ended first, as
 * Finish game does (D-8, results frozen), then the event closes, in the same transaction.
 */
final readonly class CloseEvent
{
    public function __construct(private AuditLog $auditLog, private StatePublisher $publisher, private FinishGame $finishGame) {}

    /**
     * @param  int|null  $seenVersion  G15: the version on the host's screen
     *
     * @throws ActionRefused
     */
    public function handle(Event $event, User $actor, ?int $seenVersion = null): void
    {
        DB::transaction(function () use ($event, $actor, $seenVersion): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status === EventStatus::Finished) {
                return;
            }

            SeenVersion::check($locked, $seenVersion);

            // G7: a finished game on screen only shows its results; a running one is ended.
            $onScreen = $locked->active_game_id === null ? null : Game::query()->find($locked->active_game_id);

            if ($onScreen !== null && $onScreen->status !== GameStatus::Finished) {
                $this->finishGame->handle($locked, $onScreen, $actor);
                // F15: Finish game moved the version on; keep counting from there.
                $locked->refresh();
            }

            $locked->status = EventStatus::Finished;
            // D-11: play, closing and reopening always end a replay.
            $locked->endReplay();
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);

            $this->auditLog->record(LoggedAction::EventClosed, $actor, $locked);
        });
    }
}
