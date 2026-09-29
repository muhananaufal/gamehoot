<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\OpenEvent;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

final class OpenEventController
{
    public function __invoke(Event $event, OpenEvent $openEvent): RedirectResponse
    {
        $openEvent->handle($event);

        return back()->with('status', __('events.opened'));
    }
}
