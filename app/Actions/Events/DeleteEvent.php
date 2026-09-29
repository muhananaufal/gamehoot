<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * D-3, G4: soft delete. The link is freed as {slug}--deleted-{id} so it can be reused.
 */
final readonly class DeleteEvent
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(Event $event, User $actor): void
    {
        DB::transaction(function () use ($event, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $locked->forceFill([
                'slug' => "{$locked->slug}--deleted-{$locked->id}",
                'deleted_by' => $actor->id,
            ]);
            $locked->bumpStateVersion();
            $locked->save();
            $locked->delete();

            $this->auditLog->record(LoggedAction::EventDeleted, $actor, $locked);
        });
    }
}
