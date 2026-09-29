<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\CloseEvent;
use App\Exceptions\EventHasActiveGame;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class CloseEventController
{
    public function __invoke(Event $event, CloseEvent $closeEvent, #[CurrentUser] User $user): RedirectResponse
    {
        try {
            $closeEvent->handle($event, $user);
        } catch (EventHasActiveGame) {
            return back()->withErrors(['event' => __('events.close_game_running')]);
        }

        return back()->with('status', __('events.closed'));
    }
}
