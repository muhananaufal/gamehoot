<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use Illuminate\Contracts\View\View;

/**
 * F20: the Live control page, following both event channels (F2).
 */
final class LiveController
{
    public function __invoke(Event $event): View
    {
        return view('host.events.live', ['event' => $event]);
    }
}
