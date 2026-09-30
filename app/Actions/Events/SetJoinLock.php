<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Realtime\StatePublisher;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * B-7: while locked, new claims are refused; phones that already claimed a name can reconnect.
 */
final readonly class SetJoinLock
{
    public function __construct(private AuditLog $auditLog, private StatePublisher $publisher) {}

    public function handle(Event $event, bool $locked, User $actor): void
    {
        DB::transaction(function () use ($event, $locked, $actor): void {
            $row = Event::query()->lockForUpdate()->findOrFail($event->id);

            if ($locked === ($row->join_locked_at !== null)) {
                return;
            }

            $row->join_locked_at = $locked ? now() : null;
            $row->bumpStateVersion();
            $row->save();
            $this->publisher->publish($row);

            $this->auditLog->record($locked ? LoggedAction::JoinLocked : LoggedAction::JoinUnlocked, $actor, $row);
        });
    }
}
