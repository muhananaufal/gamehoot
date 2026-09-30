<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * F1, F5: a full snapshot for one audience, sent straight to Reverb without a queue.
 * Dispatched only by StatePublisher, after the transaction commits (F22).
 *
 * @phpstan-import-type Snapshot from EventSnapshot
 */
final readonly class StateChanged implements ShouldBroadcastNow
{
    /**
     * @param  Snapshot  $snapshot
     */
    public function __construct(
        public Event $event,
        public Audience $audience,
        public array $snapshot,
    ) {}

    public function broadcastOn(): Channel
    {
        $name = $this->audience->channelName($this->event);

        return $this->audience === Audience::Host ? new PrivateChannel($name) : new Channel($name);
    }

    public function broadcastAs(): string
    {
        return 'state';
    }

    /**
     * @return Snapshot
     */
    public function broadcastWith(): array
    {
        return $this->snapshot;
    }
}
