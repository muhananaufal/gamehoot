<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Enums\Audience;
use App\Models\Event;
use App\Realtime\EventSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * F1: the public snapshot, fetched on load, after a reconnect (F17) and while polling (T2).
 * Open to anyone who knows the event link, like the public channel (F2). Not rate limited:
 * the whole venue may share one IP (F11), and the body is cached per version (F15).
 */
final class EventStateController
{
    public function __invoke(Event $event, EventSnapshot $snapshots): JsonResponse
    {
        return response()->json($snapshots->for($event, Audience::Public))
            ->header('Cache-Control', 'no-store, private');
    }
}
