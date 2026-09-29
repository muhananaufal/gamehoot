<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * B-3: the name list cannot be edited once the first game has started.
 */
final class NameListLocked extends RuntimeException
{
    public const string CODE = 'NAMES_LOCKED';

    public static function make(): self
    {
        return new self('The name list is locked because a game has started.');
    }
}
