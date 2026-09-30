<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use App\Models\Question;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;

/**
 * F14: the only message a vote sends: how many phones answered this attempt. Hosts fetch
 * the per-name tally from GET /host/{event}/state.
 */
final readonly class AnswerCounted implements ShouldBroadcastNow
{
    public function __construct(
        public Event $event,
        public Question $question,
        public int $attempt,
        public int $answered,
    ) {}

    public function broadcastOn(): Channel
    {
        return new Channel(Audience::Public->channelName($this->event));
    }

    public function broadcastAs(): string
    {
        return 'answered';
    }

    /**
     * @return array{question_id: string, attempt: int, answered: int}
     */
    public function broadcastWith(): array
    {
        return ['question_id' => $this->question->id, 'attempt' => $this->attempt, 'answered' => $this->answered];
    }
}
