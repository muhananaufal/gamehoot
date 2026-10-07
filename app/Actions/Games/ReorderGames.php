<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Enums\GameStatus;
use App\Exceptions\ActionRefused;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Support\Facades\DB;

/**
 * D-9: the games still to play take a new order. Played games (finished or on screen) keep their
 * places; the others fill the places they held, in the new order.
 */
final readonly class ReorderGames
{
    /**
     * @param  list<string>  $order  the ids of every game still to play, in the new order
     *
     * @throws ActionRefused
     */
    public function handle(Event $event, array $order): void
    {
        DB::transaction(function () use ($event, $order): void {
            // F6: one order at a time; a game started meanwhile makes the order stale.
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $toPlay = $locked->games()->orderBy('position')->get()
                ->filter(fn (Game $game): bool => self::stillToPlay($locked, $game))
                ->values();

            if ($toPlay->pluck('id')->sort()->values()->all() !== collect($order)->sort()->values()->all()) {
                throw ActionRefused::stale();
            }

            $places = $toPlay->pluck('position')->values();

            foreach ($order as $index => $id) {
                Game::query()->whereKey($id)->update(['position' => $places[$index]]);
            }
        });
    }

    /**
     * D-9: a game can move while it has not started: not finished and not on screen.
     */
    public static function stillToPlay(Event $event, Game $game): bool
    {
        return $game->status !== GameStatus::Finished && $game->id !== $event->active_game_id;
    }
}
