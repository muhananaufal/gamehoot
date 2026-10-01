<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * Draft to open: the join links start working (last step of event setup).
 */
final readonly class OpenEvent
{
    public function __construct(private StatePublisher $publisher, private AuditLog $auditLog) {}

    public function handle(Event $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status !== EventStatus::Draft) {
                return;
            }

            $locked->status = EventStatus::Open;
            $locked->bumpStateVersion();
            $locked->save();
            // E13: phones can join from now on.
            $this->auditLog->record(LoggedAction::EventOpened, $actor, $locked);
            $this->publisher->publish($locked);
        });
    }
}
