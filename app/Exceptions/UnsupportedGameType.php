<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Enums\GameType;
use LogicException;

/**
 * F13: the game type has no engine in this version.
 */
final class UnsupportedGameType extends LogicException
{
    public static function for(GameType $type): self
    {
        return new self(sprintf('Game type [%s] has no engine yet.', $type->value));
    }
}
