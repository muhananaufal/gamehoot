<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;

/**
 * F20: what a live page hands to the shared store in resources/js/app.js: where to fetch
 * the state, which channels to follow, the first snapshot, and how to reach Reverb.
 *
 * @phpstan-import-type Snapshot from EventSnapshot
 */
final readonly class ClientConfig
{
    public function __construct(private EventSnapshot $snapshots) {}

    /**
     * E4: a phone page passes its own name and vote ($me, as /state adds it), so the first
     * render already knows whether this phone voted.
     *
     * @param  array{name: string, vote: array{question_id: string, attempt: int, target: string}|null}|null  $me
     * @return array{socket: array{key: string, host: string, port: int, scheme: string}|null, stateUrl: string, channels: list<array{name: string, private: bool}>, initial: array<string, mixed>}
     */
    public function for(Event $event, Audience $audience, ?array $me = null): array
    {
        $channels = [['name' => Audience::Public->channelName($event), 'private' => false]];

        if ($audience === Audience::Host) {
            $channels[] = ['name' => Audience::Host->channelName($event), 'private' => true];
        }

        return [
            'socket' => $this->socket(),
            'stateUrl' => $audience === Audience::Host ? route('host.events.state', $event) : route('join.state', $event),
            'channels' => $channels,
            'initial' => $me === null ? $this->snapshots->for($event, $audience) : [...$this->snapshots->for($event, $audience), 'me' => $me],
        ];
    }

    /**
     * P1: the browser settings come from the running config, never from the Vite build.
     * Null means no socket server (log or null broadcaster): pages poll instead (T2).
     *
     * @return array{key: string, host: string, port: int, scheme: string}|null
     */
    private function socket(): ?array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return null;
        }

        return [
            'key' => config()->string('broadcasting.connections.reverb.key'),
            'host' => config()->string('broadcasting.connections.reverb.client.host'),
            'port' => config()->integer('broadcasting.connections.reverb.client.port'),
            'scheme' => config()->string('broadcasting.connections.reverb.client.scheme'),
        ];
    }
}
