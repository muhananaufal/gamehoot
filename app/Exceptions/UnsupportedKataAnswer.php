<?php

declare(strict_types=1);

namespace App\Exceptions;

use InvalidArgumentException;

/**
 * E5: the answer has a character that cannot be shown as a box or as punctuation,
 * or it has no box at all.
 */
final class UnsupportedKataAnswer extends InvalidArgumentException
{
    public static function character(string $char): self
    {
        return new self(sprintf('The character [%s] is not allowed in a Tebak Kata answer.', $char));
    }

    public static function empty(): self
    {
        return new self('A Tebak Kata answer needs at least one letter or digit.');
    }
}
