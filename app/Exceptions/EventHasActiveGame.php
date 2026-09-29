<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * T6: an event cannot be closed while a game is running.
 */
final class EventHasActiveGame extends RuntimeException
{
    public const string CODE = 'GAME_RUNNING';

    public static function cannotClose(): self
    {
        return new self('The event cannot be closed while a game is running.');
    }
}
