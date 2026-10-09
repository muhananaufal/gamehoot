<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\DeleteEvent;
use App\Actions\Events\SetUpEvent;
use App\Actions\Events\UpdateEventSettings;
use App\Enums\GameStatus;
use App\Enums\ScreenTheme;
use App\Http\Requests\Host\DeleteEventRequest;
use App\Http\Requests\Host\StoreEventRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

final class EventController
{
    public function create(): View
    {
        return view('host.events.create', ['themes' => ScreenTheme::cases()]);
    }

    /**
     * U26: the event, its names and its games are saved together at the last step of New event;
     * the host goes on in Live control, where the event is opened.
     */
    public function store(StoreEventRequest $request, SetUpEvent $setUpEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $event = $setUpEvent->handle(
            $user,
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
            $request->theme(),
            $request->boolean('show_on_devices'),
            $request->names(),
            $request->packs(),
        );

        return redirect()->route('host.events.live', $event)->with('status', __('events.created'));
    }

    public function edit(Event $event, #[CurrentUser] User $user): View
    {
        return view('host.events.settings', [
            'event' => $event,
            'themes' => ScreenTheme::cases(),
            // U20: the owner heads the hosts, then the co-hosts from the first added.
            'owner' => $event->owner()->firstOrFail(),
            'cohosts' => $event->cohosts()->orderBy('event_hosts.created_at')->get(),
            'isOwner' => $event->isOwnedBy($user),
            // T6, D-10: closing ends this game first; the dialog names it.
            'runningGame' => $event->activeGame()->where('status', '!=', GameStatus::Finished)->first(),
        ]);
    }

    public function update(StoreEventRequest $request, Event $event, UpdateEventSettings $updateSettings, #[CurrentUser] User $user): RedirectResponse
    {
        $updateSettings->handle(
            $event,
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
            $request->theme(),
            $request->boolean('show_on_devices'),
            $request->boolean('screen_animations'),
            $user,
        );

        return redirect()->route('host.events.edit', $event)->with('status', __('events.saved'));
    }

    public function destroy(DeleteEventRequest $request, Event $event, DeleteEvent $deleteEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $deleteEvent->handle($event, $user);

        return redirect()->route('host.dashboard')->with('status', __('events.deleted_done', ['name' => $event->name]));
    }
}
