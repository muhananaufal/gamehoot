<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-3: the owner or a super-admin restores an event under a link that is free.
 */
final readonly class RestoreEvent
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(Event $event, string $slug, User $actor): void
    {
        DB::transaction(function () use ($event, $slug, $actor): void {
            $locked = Event::withTrashed()->lockForUpdate()->findOrFail($event->id);
            $locked->forceFill(['slug' => $slug, 'deleted_by' => null]);
            $locked->bumpStateVersion();
            $locked->restore();

            $this->auditLog->record(LoggedAction::EventRestored, $actor, $locked);
        });
    }
}
