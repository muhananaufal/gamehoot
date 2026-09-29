<?php

declare(strict_types=1);

namespace App\Http\Views;

use App\Enums\EventStatus;
use App\Exceptions\ClaimRefused;
use App\Models\Event;
use Illuminate\Http\Response;

/**
 * F20: the phone status screens, each with its fixed error code (K4).
 */
final class PhoneStatus
{
    /**
     * The screen to show instead of the join page, or null when joining is possible.
     */
    public static function forEvent(Event $event): ?Response
    {
        return match (true) {
            $event->status === EventStatus::Finished => self::screen($event, 'ended', null, 200),
            $event->status === EventStatus::Draft => self::screen($event, 'not_open', 'EVENT_NOT_OPEN', 200),
            $event->join_locked_at !== null => self::screen($event, 'locked', 'CLAIMS_LOCKED', 423),
            default => null,
        };
    }

    public static function refused(Event $event, ClaimRefused $refused, string $name): Response
    {
        return match ($refused->errorCode) {
            'NAME_TAKEN' => self::screen($event, 'taken', 'NAME_TAKEN', $refused->status, ['name' => $name]),
            'CLAIMS_LOCKED' => self::screen($event, 'locked', 'CLAIMS_LOCKED', $refused->status),
            default => self::forEvent($event) ?? self::screen($event, 'not_open', 'EVENT_NOT_OPEN', 200),
        };
    }

    /**
     * T5: a personal link that was replaced, or never existed.
     */
    public static function linkInvalid(Event $event): Response
    {
        return self::screen($event, 'link_invalid', 'LINK_INVALID', 404);
    }

    public static function notFound(): Response
    {
        return response()->view('join.status', ['event' => null, 'screen' => 'not_found', 'code' => 'EVENT_NOT_FOUND', 'replace' => []], 404);
    }

    /**
     * @param  array<string, string>  $replace
     */
    private static function screen(Event $event, string $screen, ?string $code, int $status, array $replace = []): Response
    {
        return response()->view('join.status', ['event' => $event, 'screen' => $screen, 'code' => $code, 'replace' => $replace], $status);
    }
}
