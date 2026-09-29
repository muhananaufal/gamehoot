<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Events\RestoreEvent;
use App\Http\Requests\Host\RestoreEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * D-3, C-4: a super-admin sees and restores every deleted event.
 */
final class TrashController
{
    public function index(): View
    {
        return view('host.trash', [
            'events' => Event::onlyTrashed()->with(['owner', 'deletedBy'])->latest('deleted_at')->get(),
            'restoreRoute' => 'admin.trash.restore',
            'showOwner' => true,
        ]);
    }

    public function update(RestoreEventRequest $request, Event $event, RestoreEvent $restoreEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $restoreEvent->handle($event, $request->string('slug')->toString(), $user);

        return redirect()->route('admin.trash.index')->with('status', __('events.restored'));
    }
}
