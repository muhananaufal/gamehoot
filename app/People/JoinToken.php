<?php

declare(strict_types=1);

namespace App\People;

use App\Models\Person;
use Illuminate\Support\Str;

/**
 * B-1: the token of a personal link. Random (CSPRNG) and never derived from the name.
 */
final class JoinToken
{
    public const int LENGTH = 16;

    public static function generate(): string
    {
        do {
            $token = Str::random(self::LENGTH);
        } while (Person::query()->where('join_token', $token)->exists());

        return $token;
    }
}
