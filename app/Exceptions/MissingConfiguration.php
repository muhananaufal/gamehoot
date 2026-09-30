<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * K6: the app refuses to start without its required configuration.
 */
final class MissingConfiguration extends RuntimeException
{
    /**
     * @param  list<string>  $variables
     */
    public static function for(array $variables): self
    {
        return new self(sprintf('Missing required configuration: %s. Set them in .env (see .env.example).', implode(', ', $variables)));
    }
}
