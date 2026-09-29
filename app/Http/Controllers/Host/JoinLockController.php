<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\SetJoinLock;
use App\Http\Requests\Host\JoinLockRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * B-7: lock or unlock new name claims.
 */
final class JoinLockController
{
    public function __invoke(JoinLockRequest $request, Event $event, SetJoinLock $setJoinLock, #[CurrentUser] User $user): RedirectResponse
    {
        $locked = $request->boolean('locked');
        $setJoinLock->handle($event, $locked, $user);

        return back()->with('status', __($locked ? 'events.join_locked' : 'events.join_unlocked'));
    }
}
