<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * F1, F5: a full snapshot for one audience, sent straight to Reverb without a queue, or a
 * refresh signal when the snapshot is too big (F24). Dispatched only by StatePublisher,
 * after the transaction commits (F22).
 */
final readonly class StateChanged implements ShouldBroadcastNow
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public Event $event,
        public Audience $audience,
        public array $payload,
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
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return $this->payload;
    }
}
