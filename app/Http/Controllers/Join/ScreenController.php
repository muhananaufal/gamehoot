<?php

declare(strict_types=1);

namespace App\Http\Controllers\Join;

use App\Models\Event;
use Illuminate\Contracts\View\View;

/**
 * The Public View on the projector (F23 lobby). Public like the event link: it shows only
 * what the public channel carries (F2).
 */
final class ScreenController
{
    public function __invoke(Event $event): View
    {
        return view('screen.show', ['event' => $event]);
    }
}
