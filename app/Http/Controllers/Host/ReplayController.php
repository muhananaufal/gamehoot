<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\ReplayOnScreen;
use App\Http\Requests\Host\ReplayRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

/**
 * D-11: Replay on screen from Live control, and Back to live.
 */
final class ReplayController
{
    public function store(ReplayRequest $request, Event $event, ReplayOnScreen $replay): RedirectResponse
    {
        // Only games and questions of this event; anything else is a moment that cannot come back.
        $game = $event->games()->find($request->string('game')->toString());
        $question = $request->filled('question') ? $game?->questions()->find($request->string('question')->toString()) : null;

        $replay->show($event, $request->kind(), $game, $question);

        return back();
    }

    public function destroy(Event $event, ReplayOnScreen $replay): RedirectResponse
    {
        $replay->stop($event);

        return back();
    }
}
