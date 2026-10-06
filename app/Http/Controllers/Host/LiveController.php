<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Views\LiveControlPage;
use App\Models\Event;
use Illuminate\View\View;

/**
 * F20, D-10: Live control, from preparing the event to closing it.
 */
final class LiveController
{
    public function __invoke(Event $event, LiveControlPage $page): View
    {
        return view('host.events.live', $page->data($event));
    }
}
