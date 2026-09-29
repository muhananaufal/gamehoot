<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * B-1, B-7: why a phone could not claim a name. Each reason maps to one fixed code (K4).
 */
final class ClaimRefused extends RuntimeException
{
    private function __construct(public readonly string $errorCode, public readonly int $status, string $message)
    {
        parent::__construct($message);
    }

    public static function nameTaken(): self
    {
        return new self('NAME_TAKEN', 409, 'This name is already claimed on another phone.');
    }

    public static function claimsLocked(): self
    {
        return new self('CLAIMS_LOCKED', 423, 'The host has locked new claims.');
    }

    public static function eventNotOpen(): self
    {
        return new self('EVENT_NOT_OPEN', 200, 'The event is not open.');
    }
}
