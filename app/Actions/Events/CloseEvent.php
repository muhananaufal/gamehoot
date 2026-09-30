<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\LoggedAction;
use App\Exceptions\EventHasActiveGame;
use App\Models\Event;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-7: players see "This event has ended". T6: refused while a game is running.
 */
final readonly class CloseEvent
{
    public function __construct(private AuditLog $auditLog, private StatePublisher $publisher) {}

    /**
     * @throws EventHasActiveGame
     */
    public function handle(Event $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->active_game_id !== null) {
                throw EventHasActiveGame::cannotClose();
            }

            if ($locked->status === EventStatus::Finished) {
                return;
            }

            $locked->status = EventStatus::Finished;
            $locked->bumpStateVersion();
            $locked->save();
            $this->publisher->publish($locked);

            $this->auditLog->record(LoggedAction::EventClosed, $actor, $locked);
        });
    }
}
