<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\OpenEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class OpenEventController
{
    public function __invoke(Event $event, OpenEvent $openEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $openEvent->handle($event, $user);

        return back()->with('status', __('events.opened'));
    }
}
