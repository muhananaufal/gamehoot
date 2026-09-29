<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Http\Requests\Host\CohostRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\RedirectResponse;

/**
 * C-2: only the owner manages co-hosts.
 */
final class EventCohostController
{
    public function store(CohostRequest $request, Event $event): RedirectResponse
    {
        $account = $request->account();
        abort_if($account === null, 422);

        $event->cohosts()->syncWithoutDetaching([$account->id]);

        return redirect()->route('host.events.edit', $event)->with('status', __('events.cohost_added', ['name' => $account->name]));
    }

    public function destroy(Event $event, User $user): RedirectResponse
    {
        $event->cohosts()->detach($user->id);

        return redirect()->route('host.events.edit', $event)->with('status', __('events.cohost_removed', ['name' => $user->name]));
    }
}
