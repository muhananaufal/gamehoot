<?php

declare(strict_types=1);

namespace App\Actions\Games;

use App\Games\QuestionCopier;
use App\Models\Event;
use App\Models\Game;
use App\Models\QuestionPack;
use Illuminate\Support\Facades\DB;

/**
 * D-9, G9: a game is a copy of a pack, so later pack edits never change a played game.
 * New games go to the end of the event's list.
 */
final readonly class CreateGame
{
    public function __construct(private QuestionCopier $copier) {}

    public function handle(Event $event, QuestionPack $pack): Game
    {
        return DB::transaction(function () use ($event, $pack): Game {
            $locked = Event::query()->lockForUpdate()->findOrFail($event->id);
            $last = $locked->games()->max('position');

            $game = new Game([
                'type' => $pack->game_type,
                'title' => $pack->title,
                'position' => is_numeric($last) ? (int) $last + 1 : 1,
            ]);
            $game->event()->associate($locked);
            $game->sourcePack()->associate($pack);
            $game->save();

            $this->copier->copyPack($pack, $game);

            return $game;
        });
    }
}
