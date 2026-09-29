<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Events the host owns or co-hosts (C-1, C-2). A super-admin sees only these too (C-4).
 */
final class DashboardController
{
    public function __invoke(Request $request, #[CurrentUser] User $user): View
    {
        $status = EventStatus::tryFrom($request->string('status')->toString());

        $events = Event::query()
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('cohosts', fn (Builder $cohosts) => $cohosts->whereKey($user->id)))
            ->withCount(['people', 'games'])
            ->latest()
            ->get();

        return view('host.dashboard', [
            'user' => $user,
            'status' => $status,
            'counts' => $events->countBy(fn (Event $event): string => $event->status->value),
            'total' => $events->count(),
            'events' => $status === null ? $events : $events->where('status', $status)->values(),
        ]);
    }
}
