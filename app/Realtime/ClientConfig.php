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
     * @return array{socket: array{key: string, host: string, port: int, scheme: string}|null, stateUrl: string, channels: list<array{name: string, private: bool}>, initial: Snapshot}
     */
    public function for(Event $event, Audience $audience): array
    {
        $channels = [['name' => Audience::Public->channelName($event), 'private' => false]];

        if ($audience === Audience::Host) {
            $channels[] = ['name' => Audience::Host->channelName($event), 'private' => true];
        }

        return [
            'socket' => $this->socket(),
            'stateUrl' => $audience === Audience::Host ? route('host.events.state', $event) : route('join.state', $event),
            'channels' => $channels,
            'initial' => $this->snapshots->for($event, $audience),
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
