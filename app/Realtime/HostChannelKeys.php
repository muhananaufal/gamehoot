<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F26 (audit SEC-1): authorization of a private channel is checked only when a socket joins it.
 * When someone loses access to an event (co-host removed, account disabled or deleted,
 * ownership transferred), the event's host channel gets a new key inside the caller's
 * transaction; after the commit the old channel receives "rotated", so host pages that may still
 * follow the event reload and join the new one.
 */
final class HostChannelKeys
{
    public static function rotate(Event $event): void
    {
        $old = Audience::hostChannelName($event, $event->rotateHostChannelKey());

        DB::afterCommit(function () use ($event, $old): void {
            try {
                event(new HostChannelRotated($old));
            } catch (Throwable) {
                // F22: a failed broadcast never fails the action; K5: no key in the log.
                Log::warning('Realtime broadcast failed.', ['event_id' => $event->id, 'audience' => 'host']);
            }
        });
    }
}
