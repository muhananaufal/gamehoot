<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Events\TransferOwnership;
use App\Http\Requests\Host\CohostRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Container\Attributes\CurrentUser;
use Illuminate\Http\RedirectResponse;

/**
 * C-2: transfer ownership to another host.
 */
final class EventOwnerController
{
    public function store(CohostRequest $request, Event $event, TransferOwnership $transfer, #[CurrentUser] User $user): RedirectResponse
    {
        $newOwner = $request->account();
        abort_if($newOwner === null, 422);

        $transfer->handle($event, $newOwner, $user);

        return redirect()->route('host.events.edit', $event)->with('status', __('events.transferred', ['name' => $newOwner->name]));
    }
}
