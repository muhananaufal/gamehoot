<?php

declare(strict_types=1);

namespace App\Accounts;

use Closure;
use Illuminate\Support\Str;

/**
 * C-6: usernames are lower case a-z, digits, dots, underscores and hyphens, 3-30 characters,
 * without spaces. Typed usernames are read in lower case.
 */
final class Username
{
    public const string PATTERN = '/^[a-z0-9._-]{3,30}$/';

    private const int MAX = 30;

    public static function normalize(string $typed): string
    {
        return Str::lower(trim($typed));
    }

    /**
     * A username made from a name: lower case, accents to plain letters, everything outside the
     * rules dropped; a number is added while it is taken, and "user" stands in when too little
     * of the name is left.
     *
     * @param  Closure(string): bool  $taken
     */
    public static function fromName(string $name, Closure $taken): string
    {
        $base = substr((string) preg_replace('/[^a-z0-9._-]/', '', Str::lower(Str::ascii($name))), 0, self::MAX - 3);

        if (strlen($base) < 3) {
            $base = 'user';
            $number = 1;

            while ($taken($base.$number)) {
                $number++;
            }

            return $base.$number;
        }

        $candidate = $base;

        for ($number = 2; $taken($candidate); $number++) {
            $candidate = $base.$number;
        }

        return $candidate;
    }
}
