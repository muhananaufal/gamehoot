<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\ReopenEvent;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

final class ReopenEventController
{
    public function __invoke(Event $event, ReopenEvent $reopenEvent, #[CurrentUser] User $user): RedirectResponse
    {
        $reopenEvent->handle($event, $user);

        return back()->with('status', __('events.reopened'));
    }
}
