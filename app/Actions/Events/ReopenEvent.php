<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\EventStatus;
use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-7: finished games stay finished and the name list stays locked; phones reconnect by broadcast.
 */
final readonly class ReopenEvent
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(Event $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked->status !== EventStatus::Finished) {
                return;
            }

            $locked->status = EventStatus::Open;
            $locked->bumpStateVersion();
            $locked->save();

            $this->auditLog->record(LoggedAction::EventReopened, $actor, $locked);
        });
    }
}
