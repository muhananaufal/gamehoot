<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\ManageCohosts;
use App\Http\Requests\Host\CohostRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * C-2: only the owner manages co-hosts.
 */
final class EventCohostController
{
    public function store(CohostRequest $request, Event $event, ManageCohosts $cohosts, #[CurrentUser] User $actor): RedirectResponse
    {
        $account = $request->account();
        abort_if($account === null, 422);

        $cohosts->add($event, $account, $actor);

        return redirect()->route('host.events.edit', $event)->with('status', __('events.cohost_added', ['name' => $account->name]));
    }

    public function destroy(Event $event, User $user, ManageCohosts $cohosts, #[CurrentUser] User $actor): RedirectResponse
    {
        $cohosts->remove($event, $user, $actor);

        return redirect()->route('host.events.edit', $event)->with('status', __('events.cohost_removed', ['name' => $user->name]));
    }
}
