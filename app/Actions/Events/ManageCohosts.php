<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Enums\LoggedAction;
use App\Models\Event;
use App\Models\User;
use App\Realtime\HostChannelKeys;
use App\Support\AuditLog;
use Illuminate\Support\Facades\DB;

/**
 * C-2: the owner gives and takes co-host access; both are logged, because they change who can
 * run the event (E13).
 */
final readonly class ManageCohosts
{
    public function __construct(private AuditLog $auditLog) {}

    public function add(Event $event, User $account, User $actor): void
    {
        DB::transaction(function () use ($event, $account, $actor): void {
            $added = $event->cohosts()->syncWithoutDetaching([$account->id])['attached'];

            if ($added !== []) {
                $this->auditLog->record(LoggedAction::CohostAdded, $actor, $event, ['account_id' => $account->id]);
            }
        });
    }

    public function remove(Event $event, User $account, User $actor): void
    {
        DB::transaction(function () use ($event, $account, $actor): void {
            if ($event->cohosts()->detach($account->id) > 0) {
                $this->auditLog->record(LoggedAction::CohostRemoved, $actor, $event, ['account_id' => $account->id]);
                // F26: a Live control the removed co-host still has open stops receiving host data.
                HostChannelKeys::rotate($event);
            }
        });
    }
}
