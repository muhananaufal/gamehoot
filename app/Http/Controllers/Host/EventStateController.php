<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Enums\Audience;
use App\Models\Event;
use App\Realtime\EventSnapshot;
use Illuminate\Http\JsonResponse;

/**
 * F2, F14: the host snapshot, with the details kept off the public channel.
 */
final class EventStateController
{
    public function __invoke(Event $event, EventSnapshot $snapshots): JsonResponse
    {
        return response()->json($snapshots->for($event, Audience::Host))
            ->header('Cache-Control', 'no-store, private');
    }
}
