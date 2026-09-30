<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * F1: the whole state a screen needs, sent on every change and returned by /state.
 * F15: carries state_version, and the body is cached per version, so hundreds of phones
 * asking at once cost one computation. server_now (F4) and the game counters that change
 * without a version bump (F24) are added fresh on every call.
 *
 * @phpstan-type EventPart array{name: string, status: string, link: string, claims_locked: bool, screen_theme: string, show_on_devices: bool}
 * @phpstan-type Body array{version: int, event: EventPart, lobby: array{joined: int}, game: array<string, mixed>|null, host?: array{names: int}}
 * @phpstan-type Snapshot array{server_now: int, version: int, event: EventPart, lobby: array{joined: int}, game: array<string, mixed>|null, host?: array{names: int}}
 */
final readonly class EventSnapshot
{
    /**
     * F14: Reverb refuses messages above max_request_size (10,000 bytes by default).
     * Every snapshot, wrapped in its message envelope, must stay under this budget.
     */
    public const int MAX_MESSAGE_BYTES = 5_000;

    private const int CACHE_SECONDS = 600;

    public function __construct(private Cache $cache, private GameEngines $engines) {}

    /**
     * @return Snapshot
     */
    public function for(Event $event, Audience $audience): array
    {
        $body = $this->cache->remember(
            "state:{$event->id}:{$event->state_version}:{$audience->value}",
            self::CACHE_SECONDS,
            fn (): array => $this->build($event, $audience),
        );

        if ($body['game'] !== null) {
            $game = $event->activeGame()->first();

            if ($game instanceof Game) {
                $body['game'] = [...$body['game'], ...$this->engines->live($game->type)->liveCounters($game, $audience)];
            }
        }

        return ['server_now' => now()->getTimestampMs(), ...$body];
    }

    /**
     * @return Body
     */
    private function build(Event $event, Audience $audience): array
    {
        $public = [
            'version' => $event->state_version,
            'event' => [
                'name' => $event->name,
                // D-3: a deleted event tells open screens it is gone.
                'status' => $event->trashed() ? 'deleted' : $event->status->value,
                'link' => route('join.index', $event->originalSlug()),
                'claims_locked' => $event->join_locked_at !== null,
                'screen_theme' => $event->screen_theme->value,
                // E14: phones mirror the Tebak screens only when the host turned this on.
                'show_on_devices' => $event->show_on_devices,
            ],
            // F23: the Public View lobby counts claimed names.
            'lobby' => ['joined' => $event->people()->whereNotNull('claimed_at')->count()],
            // D-1, F13: the active game, drawn by its engine.
            'game' => null,
        ];

        $game = $event->trashed() ? null : $event->activeGame()->first();

        if ($game instanceof Game) {
            $public['game'] = $this->engines->live($game->type)->snapshot($game, $audience);
        }

        if ($audience === Audience::Public) {
            return $public;
        }

        return [...$public, 'host' => ['names' => $event->people()->count()]];
    }
}
