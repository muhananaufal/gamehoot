<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\User;

/**
 * E13: records who did what, when and where. K5: the payload holds ids only, never
 * participant names, claim tokens or personal link tokens.
 */
final class AuditLog
{
    /**
     * @param  array<string, int|string|null>  $payload
     */
    public function record(LoggedAction $action, ?User $actor, ?Event $event = null, array $payload = []): void
    {
        $log = new ActionLog([
            'action' => $action,
            'payload' => $payload === [] ? null : $payload,
        ]);
        $log->user()->associate($actor);
        $log->event()->associate($event);
        $log->save();
    }
}
