<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Games\GameEngines;
use App\Games\LiveState;
use App\Games\Replay\ReplayMoments;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Contracts\Cache\Repository as Cache;

/**
 * F1: the whole state a screen needs, sent on every change and returned by /state.
 * F15: carries state_version, and the body is cached per version, so hundreds of phones
 * asking at once cost one computation. server_now (F4), the lobby count (F25) and the game
 * counters that change without a version bump (F24) are added fresh on every call.
 * Audit PERF-1: one cache entry per event and audience, holding its version; a new version
 * overwrites it, so the cache does not grow with every change.
 *
 * @phpstan-type EventPart array{name: string, status: string, link: string, claims_locked: bool, screen_theme: string, show_on_devices: bool, screen_animations: bool, roster: int}
 *
 * @phpstan-import-type Payload from ReplayMoments
 *
 * @phpstan-type Body array{version: int, event: EventPart, game: array<string, mixed>|null, replay: Payload|null, host?: array{names: int}}
 * @phpstan-type Snapshot array{server_now: int, version: int, event: EventPart, lobby: array{joined: int}, game: array<string, mixed>|null, replay: Payload|null, host?: array{names: int}}
 */
final readonly class EventSnapshot
{
    /**
     * F14: Reverb refuses messages above max_request_size (10,000 bytes by default).
     * Every snapshot, wrapped in its message envelope, must stay under this budget.
     */
    public const int MAX_MESSAGE_BYTES = 5_000;

    private const int CACHE_SECONDS = 600;

    public function __construct(private Cache $cache, private GameEngines $engines, private ReplayMoments $replays) {}

    public static function cacheKey(Event $event, Audience $audience): string
    {
        return "state:{$event->id}:{$audience->value}";
    }

    /**
     * @return Snapshot
     */
    public function for(Event $event, Audience $audience, ?LiveState $live = null): array
    {
        $body = $this->body($event, $audience);
        // F23, F25: claims do not bump the version, so the lobby count is always fresh.
        $lobby = ['joined' => $event->people()->whereNotNull('claimed_at')->count()];

        if ($body['game'] !== null) {
            $live ??= LiveState::of($event);

            if ($live->game instanceof Game) {
                $body['game'] = [...$body['game'], ...$this->engines->live($live->game->type)->liveCounters($live, $audience)];
            }
        }

        $snapshot = [
            'server_now' => now()->getTimestampMs(),
            'version' => $body['version'],
            'event' => $body['event'],
            'lobby' => $lobby,
            'game' => $body['game'],
            'replay' => $body['replay'],
        ];

        return isset($body['host']) ? [...$snapshot, 'host' => $body['host']] : $snapshot;
    }

    /**
     * @return Body
     */
    private function body(Event $event, Audience $audience): array
    {
        $key = self::cacheKey($event, $audience);
        $cached = $this->cache->get($key);

        if (is_array($cached) && ($cached['version'] ?? null) === $event->state_version) {
            /** @var Body $cached checked by version: written below for this very state */
            return $cached;
        }

        $body = $this->build($event, $audience);
        $this->cache->put($key, $body, self::CACHE_SECONDS);

        return $body;
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
                // U31: the Public View plays idle bursts and confetti only when this is on.
                'screen_animations' => $event->screen_animations,
                // B-6: moves when the host changes the name list (not on claims).
                'roster' => $event->roster_version,
            ],
            // D-1, F13: the active game, drawn by its engine.
            'game' => null,
            // D-11: the past moment the Public View replays (at most ten names, F14). Phones ignore it.
            'replay' => $event->trashed() ? null : $this->replays->payload($event),
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
