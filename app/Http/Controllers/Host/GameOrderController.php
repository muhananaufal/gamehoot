<?php

declare(strict_types=1);

namespace App\Http\Controllers\Host;

use App\Actions\Games\ReorderGames;
use App\Http\Requests\Host\ReorderGamesRequest;
use App\Models\Event;
use Illuminate\Http\RedirectResponse;

/**
 * D-9: the order of the games still to play.
 */
final class GameOrderController
{
    public function store(ReorderGamesRequest $request, Event $event, ReorderGames $reorder): RedirectResponse
    {
        $reorder->handle($event, $request->order());

        return back()->with('status', __('games.reordered'));
    }
}
