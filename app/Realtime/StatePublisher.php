<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use App\Models\Question;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F22: actions call publish() inside their transaction, after bumping state_version (F15).
 * The snapshots go out once the transaction commits; nothing is sent for a rollback.
 * A failed broadcast never fails the action: clients catch up through /state (F17, T2).
 * F24: a snapshot above the F14 budget goes out as a refresh signal instead.
 */
final readonly class StatePublisher
{
    public function __construct(
        private Dispatcher $events,
        private EventSnapshot $snapshots,
    ) {}

    public function publish(Event $event): void
    {
        DB::afterCommit(function () use ($event): void {
            foreach (Audience::cases() as $audience) {
                try {
                    $snapshot = $this->snapshots->for($event, $audience);
                    $payload = self::fits($audience->channelName($event), $snapshot)
                        ? $snapshot
                        : ['server_now' => $snapshot['server_now'], 'version' => $snapshot['version'], 'refresh' => true];

                    $this->events->dispatch(new StateChanged($event, $audience, $payload));
                } catch (Throwable $exception) {
                    // K5: the event id only, never names or tokens.
                    Log::warning('Realtime broadcast failed.', [
                        'event_id' => $event->id,
                        'audience' => $audience->value,
                        'error' => $exception->getMessage(),
                    ]);
                }
            }
        });
    }

    /**
     * F14: the per-vote counter, sent after the vote commits. It does not bump state_version
     * (F24); /state counts the votes fresh.
     */
    public function publishAnswered(Event $event, Question $question, int $attempt): void
    {
        DB::afterCommit(function () use ($event, $question, $attempt): void {
            try {
                $answered = $question->votes()->where('attempt', $attempt)->count();
                $this->events->dispatch(new AnswerCounted($event, $question, $attempt, $answered));
            } catch (Throwable $exception) {
                Log::warning('Realtime broadcast failed.', [
                    'event_id' => $event->id,
                    'audience' => Audience::Public->value,
                    'error' => $exception->getMessage(),
                ]);
            }
        });
    }

    /**
     * F14: the size of the message Reverb receives: the payload is JSON-encoded inside the
     * JSON envelope, so quotes are escaped twice.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fits(string $channel, array $payload): bool
    {
        $message = json_encode(['event' => 'state', 'channel' => $channel, 'data' => json_encode($payload, JSON_THROW_ON_ERROR)], JSON_THROW_ON_ERROR);

        return strlen($message) < EventSnapshot::MAX_MESSAGE_BYTES;
    }
}
