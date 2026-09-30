<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Results\EventActivity;
use Illuminate\Contracts\View\View;

/**
 * E13: the activity log of an event, for its hosts.
 */
final class ActivityLogController
{
    public function __invoke(Event $event): View
    {
        return view('host.logs.index', ['event' => $event, 'entries' => EventActivity::for($event)]);
    }
}
