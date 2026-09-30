<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// F2, F18: the public channel (event.{uuid}.public) needs no authorization. The host
// channel is for the owner and co-hosts (C-2); a deleted event does not resolve.
Broadcast::channel('event.{event}.host', fn (User $user, Event $event): bool => $user->can('view', $event));
