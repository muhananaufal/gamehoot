<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// F2, F18: the public channel (event.{uuid}.public) and the counter channel (F25) need no
// authorization. The host channel is for the owner and co-hosts (C-2) and carries the event's
// current key (F26); a deleted event does not resolve, and an old key is refused.
Broadcast::channel(
    'event.{event}.host.{key}',
    fn (User $user, Event $event, string $key): bool => $user->can('view', $event) && hash_equals($event->hostChannelKey(), $key),
);
