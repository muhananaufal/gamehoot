<?php

declare(strict_types=1);

namespace App\Realtime;

use App\Enums\Audience;
use App\Models\Event;
use App\Models\Question;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * F22: actions call publish() inside their transaction, after bumping state_version (F15).
 * The snapshots go out once the transaction commits; nothing is sent for a rollback.
 * A failed broadcast never fails the action: clients catch up through /state (F17, T2).
 * F24: a snapshot above the F14 budget goes out as a refresh signal instead.
 * F22 (audit PERF-4): one transaction sends at most one set of snapshots per event, even when
 * an action runs another one first (Close finishes the running game); the last bumped model
 * is sent. Bound as a scoped instance, so nested actions share it.
 */
final class StatePublisher
{
    /**
     * Events whose snapshots wait for the commit, with the latest model each action passed.
     *
     * @var array<string, Event>
     */
    private array $pending = [];

    /**
     * The dispatcher is looked up when a message goes out, not kept: the instance lives for the
     * whole request (scoped), and a swapped dispatcher must still be used.
     */
    public function __construct(private readonly EventSnapshot $snapshots) {}

    public function publish(Event $event): void
    {
        $scheduled = array_key_exists($event->id, $this->pending);
        $this->pending[$event->id] = $event;

        if ($scheduled) {
            return;
        }

        DB::afterRollBack(function () use ($event): void {
            unset($this->pending[$event->id]);
        });

        DB::afterCommit(function () use ($event): void {
            $latest = $this->pending[$event->id] ?? $event;
            unset($this->pending[$event->id]);
            $this->send($latest);
        });
    }

    /**
     * F23, F25: a claim changes only the lobby count. It goes to the counter channel as one
     * number after the claim commits, without a snapshot or a version bump (F15).
     */
    public function publishJoined(Event $event): void
    {
        DB::afterCommit(function () use ($event): void {
            try {
                $joined = $event->people()->whereNotNull('claimed_at')->count();
                event(new JoinedCounted($event, $joined));
            } catch (Throwable $exception) {
                Log::warning('Realtime broadcast failed.', [
                    'event_id' => $event->id,
                    'audience' => 'screen',
                    'error' => self::describe($exception),
                ]);
            }
        });
    }

    private function send(Event $event): void
    {
        foreach (Audience::cases() as $audience) {
            try {
                $snapshot = $this->snapshots->for($event, $audience);
                $payload = self::fits($audience->channelName($event), $snapshot)
                    ? $snapshot
                    : ['server_now' => $snapshot['server_now'], 'version' => $snapshot['version'], 'refresh' => true];

                event(new StateChanged($event, $audience, $payload));
            } catch (Throwable $exception) {
                // K5: the event id only, never names or tokens.
                Log::warning('Realtime broadcast failed.', [
                    'event_id' => $event->id,
                    'audience' => $audience->value,
                    'error' => self::describe($exception),
                ]);
            }
        }
    }

    /**
     * F14: the per-vote counter, sent after the vote commits. It does not bump state_version
     * (F24); /state counts the votes fresh. F25: on the counter channel.
     */
    public function publishAnswered(Event $event, Question $question, int $attempt): void
    {
        DB::afterCommit(function () use ($event, $question, $attempt): void {
            try {
                $answered = $question->votes()->where('attempt', $attempt)->count();
                event(new AnswerCounted($event, $question, $attempt, $answered));
            } catch (Throwable $exception) {
                Log::warning('Realtime broadcast failed.', [
                    'event_id' => $event->id,
                    'audience' => 'screen',
                    'error' => self::describe($exception),
                ]);
            }
        });
    }

    /**
     * The Pusher client puts the signed request URL in its message; the query string (auth key
     * and signature) stays out of the log.
     */
    private static function describe(Throwable $exception): string
    {
        return preg_replace('/\?\S*/', '', $exception->getMessage()) ?? $exception::class;
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
