<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\CloseEvent;
use App\Models\Event;
use App\Models\User;
use App\Support\SeenVersion;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * D-7: close the event. G15: refused as stale when another host changed the screen since this
 * host last saw it.
 */
final class CloseEventController
{
    public function __invoke(Request $request, Event $event, CloseEvent $closeEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $closeEvent->handle($event, $user, SeenVersion::from($request));

        return back()->with('status', __('events.closed'));
    }
}
