<?php

declare(strict_types=1);

namespace App\People;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Cookie;

/**
 * B-1, G3: the phone keeps the raw claim token in a cookie (encrypted by Laravel, HttpOnly);
 * the database keeps only its SHA-256 hash. One cookie per event.
 */
final class ClaimCookie
{
    /** Long enough to outlive an event day and a reopen (D-7). */
    private const int MINUTES = 60 * 24 * 30;

    /**
     * @return non-empty-string
     */
    public static function name(Event $event): string
    {
        return 'pentahoot_claim_'.$event->id;
    }

    public static function newToken(): string
    {
        return Str::random(40);
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    public static function make(Event $event, string $token): Cookie
    {
        return cookie(self::name($event), $token, self::MINUTES, httpOnly: true, sameSite: 'lax');
    }

    /**
     * The name this phone claimed in the event, or null when it has no valid claim
     * (never claimed, or released by a host).
     */
    public static function person(Request $request, Event $event): ?Person
    {
        $token = $request->cookie(self::name($event));

        if (! is_string($token) || $token === '') {
            return null;
        }

        return $event->people()->where('claim_token_hash', self::hash($token))->first();
    }
}
