<?php

declare(strict_types=1);

use App\Enums\EventPhase;
use App\Enums\EventStatus;
use App\Enums\GameStatus;

// D-10: the phase Live control shows, derived from the stored status, the game on screen and the games.
it('derives the phase of an event', function (EventStatus $status, ?GameStatus $onScreen, int $games, int $unplayed, EventPhase $phase): void {
    expect(EventPhase::of($status, $onScreen, $games, $unplayed))->toBe($phase);
})->with([
    'draft' => [EventStatus::Draft, null, 2, 2, EventPhase::Draft],
    'open without games' => [EventStatus::Open, null, 0, 0, EventPhase::Open],
    'open, games waiting' => [EventStatus::Open, null, 2, 2, EventPhase::Open],
    'a game runs' => [EventStatus::Open, GameStatus::Draft, 2, 1, EventPhase::Playing],
    'a finished game on screen, one waiting' => [EventStatus::Open, GameStatus::Finished, 2, 1, EventPhase::Open],
    'every game played' => [EventStatus::Open, GameStatus::Finished, 2, 0, EventPhase::AllPlayed],
    'closed' => [EventStatus::Finished, GameStatus::Finished, 2, 0, EventPhase::Finished],
]);
