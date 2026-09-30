<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\GameType;
use LogicException;

/**
 * F13: the engine of this game type cannot run it live.
 */
final class UnsupportedGameType extends LogicException
{
    public static function notPlayable(GameType $type): self
    {
        return new self(sprintf('Game type [%s] cannot be played live yet.', $type->value));
    }
}
