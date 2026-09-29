<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Enums\EventStatus;
use App\Http\Views\PhoneStatus;
use App\Models\Event;
use App\People\ClaimCookie;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The player's screen. Stage 1 shows the waiting room; the realtime game screens come with
 * the realtime stage (spec section 12).
 */
final class PlayController
{
    public function __invoke(Request $request, Event $event): Response|RedirectResponse
    {
        $person = ClaimCookie::person($request, $event);

        if ($person === null) {
            return redirect()->route('join.index', $event);
        }

        // D-3: the claim stays, access is refused only while the event is finished.
        if ($event->status === EventStatus::Finished) {
            return PhoneStatus::forEvent($event) ?? redirect()->route('join.index', $event);
        }

        return response()->view('join.play', ['event' => $event, 'person' => $person]);
    }
}
