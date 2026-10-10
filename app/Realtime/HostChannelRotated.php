<?php

declare(strict_types=1);

namespace App\Realtime;

use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * F26: the last message on a host channel whose key was replaced. Host pages reload on it and
 * join the channel with the new key if they still may; sockets of hosts who lost access stay on
 * the old channel, where nothing more is sent.
 */
final readonly class HostChannelRotated implements ShouldBroadcastNow
{
    public function __construct(public string $channel) {}

    public function broadcastOn(): PrivateChannel
    {
        // PrivateChannel adds the "private-" prefix that Echo and Reverb use.
        return new PrivateChannel($this->channel);
    }

    public function broadcastAs(): string
    {
        return 'rotated';
    }

    /**
     * @return array{}
     */
    public function broadcastWith(): array
    {
        return [];
    }
}
