<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Event;
use App\Rules\EventSlug;
use Illuminate\Support\Str;

/**
 * D-3, F10: a link made from the event name when the host leaves it empty. The host did
 * not pick it, so instead of failing it is made valid and free: a reserved or too short
 * result gets "-event", a taken one gets -2, -3, ...
 */
final class EventLink
{
    private const int MAX_LENGTH = 60;

    public static function fromName(string $name, ?Event $ignore = null): string
    {
        $base = rtrim(Str::limit(Str::slug($name), self::MAX_LENGTH, ''), '-');

        if ($base === '') {
            $base = 'event';
        } elseif (strlen($base) < 3 || in_array($base, EventSlug::RESERVED, true)) {
            $base .= '-event';
        }

        $candidate = $base;

        for ($n = 2; self::taken($candidate, $ignore); $n++) {
            $suffix = "-{$n}";
            $candidate = rtrim(substr($base, 0, self::MAX_LENGTH - strlen($suffix)), '-').$suffix;
        }

        return $candidate;
    }

    private static function taken(string $slug, ?Event $ignore): bool
    {
        return Event::query()
            ->where('slug', $slug)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore?->id))
            ->exists();
    }
}
