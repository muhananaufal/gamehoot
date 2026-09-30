<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Results\EventResults;
use Illuminate\Contracts\View\View;

/**
 * D-3, G10: the results page, read from the frozen tables.
 */
final class ResultsController
{
    public function __invoke(Event $event): View
    {
        return view('host.results.index', ['event' => $event, 'results' => EventResults::for($event)]);
    }
}
