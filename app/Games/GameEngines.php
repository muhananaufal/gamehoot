<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\GameType;
use App\Exceptions\UnsupportedGameType;

/**
 * F13: resolves the engine for a game type. Tebak Gambar joins in stage 5 together with
 * image uploads (E8, E9).
 */
final readonly class GameEngines
{
    public function __construct(
        private PentahootEngine $pentahoot,
        private TebakKataEngine $tebakKata,
    ) {}

    /**
     * @throws UnsupportedGameType
     */
    public function for(GameType $type): GameEngine
    {
        return match ($type) {
            GameType::Pentahoot => $this->pentahoot,
            GameType::TebakKata => $this->tebakKata,
            GameType::TebakGambar => throw UnsupportedGameType::for($type),
        };
    }

    /**
     * @throws UnsupportedGameType when the type cannot be played live yet
     */
    public function live(GameType $type): LiveGameEngine
    {
        $engine = $this->for($type);

        if (! $engine instanceof LiveGameEngine) {
            throw UnsupportedGameType::notPlayable($type);
        }

        return $engine;
    }

    /**
     * @return list<GameType>
     */
    public function supportedTypes(): array
    {
        return [GameType::Pentahoot, GameType::TebakKata];
    }

    /**
     * Types a game can be created from and played live.
     *
     * @return list<GameType>
     */
    public function playableTypes(): array
    {
        return array_values(array_filter(
            $this->supportedTypes(),
            fn (GameType $type): bool => $this->for($type) instanceof LiveGameEngine,
        ));
    }
}
