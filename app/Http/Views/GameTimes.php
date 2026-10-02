<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use Carbon\CarbonInterface;

/**
 * U6: when each game of an event started, read from the activity log (E13), so no game column
 * is added. A game is started once (D-1); the first entry wins if a log ever holds two.
 */
final class GameTimes
{
    /**
     * @return array<string, CarbonInterface> game id => start time
     */
    public static function startedFor(Event $event): array
    {
        $started = [];

        $logs = ActionLog::query()
            ->whereBelongsTo($event)
            ->where('action', LoggedAction::GameStarted)
            ->oldest()
            ->get(['payload', 'created_at']);

        foreach ($logs as $log) {
            $gameId = $log->payload['game_id'] ?? null;

            if (is_string($gameId) && $log->created_at !== null && ! isset($started[$gameId])) {
                $started[$gameId] = $log->created_at;
            }
        }

        return $started;
    }
}
