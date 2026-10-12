<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * F23, F25: the message a claim sends: how many names are claimed now. It goes to the counter
 * channel only (the Public View lobby and Live control), never to the phones, and does not
 * bump state_version (F15).
 */
final readonly class JoinedCounted implements ShouldBroadcastNow
{
    public function __construct(public Event $event, public int $joined) {}

    public function broadcastOn(): Channel
    {
        return new Channel(Audience::counterChannelName($this->event));
    }

    public function broadcastAs(): string
    {
        return 'joined';
    }

    /**
     * @return array{joined: int}
     */
    public function broadcastWith(): array
    {
        return ['joined' => $this->joined];
    }
}
