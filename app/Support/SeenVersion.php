<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\ActionRefused;
use App\Models\Event;
use Illuminate\Http\Request;

/**
 * G15 (audit FLOW-1): actions that destroy or decide something (Reset votes, Pick winner,
 * Surrender, Finish game, Close event) carry the state_version the host was looking at. When
 * another host changed the screen in between, the action is refused as stale, and the host sees
 * the new state before deciding again.
 */
final class SeenVersion
{
    /**
     * The version the page sent, or -1 when it sent none (an old tab is treated as stale).
     */
    public static function from(Request $request): int
    {
        $version = $request->input('version');

        return is_numeric($version) ? (int) $version : -1;
    }

    /**
     * Called with the event row locked (G12). Null means the caller is not a host's screen (an
     * action run by another action, a console command), so there is nothing to compare.
     *
     * @throws ActionRefused
     */
    public static function check(Event $locked, ?int $seen): void
    {
        if ($seen !== null && $seen !== $locked->state_version) {
            throw ActionRefused::stale();
        }
    }
}
