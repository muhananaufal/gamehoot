<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\RestoreEvent;
use App\Http\Requests\Host\RestoreEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * D-3: deleted events of the signed-in owner. Co-hosts cannot restore (C-2).
 */
final class TrashController
{
    public function index(#[CurrentUser] User $user): View
    {
        return view('host.trash', [
            'events' => Event::onlyTrashed()->where('owner_id', $user->id)->with(['owner', 'deletedBy'])->latest('deleted_at')->get(),
            'restoreRoute' => 'host.trash.restore',
            'showOwner' => false,
        ]);
    }

    public function update(RestoreEventRequest $request, Event $event, RestoreEvent $restoreEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $restoreEvent->handle($event, $request->string('slug')->toString(), $user);

        return redirect()->route('host.events.edit', $event)->with('status', __('events.restored'));
    }
}
