<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\View\View;

/**
 * Events the host owns or co-hosts (C-1, C-2). A super-admin sees only these too (C-4).
 */
final class DashboardController
{
    public function __invoke(#[CurrentUser] User $user): View
    {
        // U4: every event is sent; filter, search and sort happen in the browser.
        $events = Event::query()
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('cohosts', fn (Builder $cohosts) => $cohosts->whereKey($user->id)))
            ->with('owner:id,name')
            ->withCount(['people', 'games'])
            ->latest()
            ->get();

        return view('host.dashboard', [
            'user' => $user,
            'counts' => $events->countBy(fn (Event $event): string => $event->status->value),
            'total' => $events->count(),
            'events' => $events,
        ]);
    }
}
