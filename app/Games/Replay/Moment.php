<?php

declare(strict_types=1);

namespace App\Games\Replay;

use App\Enums\ReplayKind;

/**
 * D-11: one past moment a host can bring back to the Public View, as Live control lists it.
 */
final readonly class Moment
{
    /**
     * @param  ?int  $number  the position of the question in its game; null for a final podium
     * @param  string  $title  the Pentahoot category, the Tebak answer, or the game title for a final podium
     * @param  list<string>  $names  the winners shown with it, none for a surrendered question
     */
    public function __construct(
        public ReplayKind $kind,
        public string $gameId,
        public ?string $questionId,
        public ?int $number,
        public string $title,
        public array $names,
    ) {}

    public function is(ReplayKind $kind, string $gameId, ?string $questionId): bool
    {
        return $this->kind === $kind && $this->gameId === $gameId && $this->questionId === $questionId;
    }
}
