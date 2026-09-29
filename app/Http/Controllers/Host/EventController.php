<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\DeleteEvent;
use App\Actions\Events\UpdateEventSettings;
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

    public function store(StoreEventRequest $request, CreateEvent $createEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $event = $createEvent->handle(
            $user,
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
            $request->theme(),
            $request->boolean('show_on_devices'),
        );

        return redirect()->route('host.events.edit', $event)->with('status', __('events.created'));
    }

    public function edit(Event $event, #[CurrentUser] User $user): View
    {
        return view('host.events.settings', [
            'event' => $event,
            'themes' => ScreenTheme::cases(),
            'cohosts' => $event->cohosts()->orderBy('name')->get(),
            'isOwner' => $event->isOwnedBy($user),
        ]);
    }

    public function update(StoreEventRequest $request, Event $event, UpdateEventSettings $updateSettings): RedirectResponse
    {
        $updateSettings->handle(
            $event,
            $request->string('name')->toString(),
            $request->string('slug')->toString(),
            $request->theme(),
            $request->boolean('show_on_devices'),
        );

        return redirect()->route('host.events.edit', $event)->with('status', __('events.saved'));
    }

    public function destroy(DeleteEventRequest $request, Event $event, DeleteEvent $deleteEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $deleteEvent->handle($event, $user);

        return redirect()->route('host.dashboard')->with('status', __('events.deleted_done', ['name' => $event->name]));
    }
}
