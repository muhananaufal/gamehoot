<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * U1: the events a host owns or co-hosts, last changed first, for the sidebar. The event being
 * worked on is always included, even when it is older than the others.
 */
final class RecentEvents
{
    public const int LIMIT = 5;

    /**
     * @return Collection<int, Event>
     */
    public static function for(User $user, ?Event $current = null): Collection
    {
        $events = Event::query()
            ->where(fn (Builder $query) => $query
                ->where('owner_id', $user->id)
                ->orWhereHas('cohosts', fn (Builder $cohosts) => $cohosts->whereKey($user->id)))
            // D-10: finished events leave the list; the one open on screen is added below.
            ->where('status', '!=', EventStatus::Finished)
            ->latest('updated_at')
            ->limit(self::LIMIT)
            ->get(['id', 'name', 'status', 'updated_at']);

        if ($current !== null && ! $events->contains('id', $current->id)) {
            $events->prepend($current);
        }

        return $events;
    }
}
