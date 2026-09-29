<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * C-2: the new owner leaves the co-host list and the previous owner joins it.
 */
final readonly class TransferOwnership
{
    public function __construct(private AuditLog $auditLog) {}

    public function handle(Event $event, User $newOwner, User $actor): void
    {
        DB::transaction(function () use ($event, $newOwner, $actor): void {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $previousOwnerId = $locked->owner_id;

            $locked->cohosts()->detach($newOwner->id);
            $locked->owner()->associate($newOwner)->save();
            $locked->cohosts()->syncWithoutDetaching([$previousOwnerId]);

            $this->auditLog->record(LoggedAction::OwnershipTransferred, $actor, $locked, [
                'from_user_id' => $previousOwnerId,
                'to_user_id' => $newOwner->id,
            ]);
        });
    }
}
