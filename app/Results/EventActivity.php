<?php

declare(strict_types=1);

namespace App\Results;

use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\Person;
use App\Models\QuestionGambar;
use App\Models\QuestionKata;
use App\Models\QuestionPentahoot;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * E13: the logged actions of an event, newest first: when, who, what, and on which
 * question, game or name. Shown to hosts only; K5 concerns the application log files.
 *
 * @phpstan-type Entry array{at: CarbonImmutable, actor_id: string, actor: string, action: string, subject: ?string}
 * @phpstan-type Actor array{id: string, name: string}
 */
final class EventActivity
{
    private const string SYSTEM = 'system';

    /**
     * U4: the choices of the Who filter: the owner, then the co-hosts from the first added, then
     * anyone else in the log (a removed co-host, a super-admin, the system).
     *
     * @param  list<Entry>  $entries
     * @return list<Actor>
     */
    public static function actors(Event $event, array $entries): array
    {
        $hosts = $event->cohosts()->orderBy('event_hosts.created_at')->get(['users.id', 'users.name']);
        $owner = $event->owner()->first(['id', 'name']);
        $actors = [];

        foreach ([$owner, ...$hosts] as $user) {
            if ($user instanceof User) {
                $actors[$user->id] = ['id' => $user->id, 'name' => $user->name];
            }
        }

        foreach ($entries as $entry) {
            $actors[$entry['actor_id']] ??= ['id' => $entry['actor_id'], 'name' => $entry['actor']];
        }

        return array_values($actors);
    }

    /**
     * @return list<Entry>
     */
    public static function for(Event $event): array
    {
        $logs = ActionLog::query()
            ->whereBelongsTo($event)
            ->with('user:id,name')
            ->latest('id')
            ->get();

        return array_values($logs->map(fn (ActionLog $log): array => [
            'at' => CarbonImmutable::instance($log->created_at ?? now()),
            // U4: entries without a user (the system) share one id for the Who filter.
            'actor_id' => $log->user_id ?? self::SYSTEM,
            'actor' => $log->user->name ?? __('logs.system'),
            'action' => __('logs.actions.'.str_replace('.', '_', $log->action->value)),
            'subject' => self::subject($log),
        ])->all());
    }

    private static function subject(ActionLog $log): ?string
    {
        $payload = is_array($log->payload) ? $log->payload : [];

        if (isset($payload['question_id']) && is_string($payload['question_id'])) {
            return self::text(QuestionPentahoot::query()->whereKey($payload['question_id'])->value('prompt'))
                ?? self::text(QuestionKata::query()->whereKey($payload['question_id'])->value('prompt'))
                ?? self::text(QuestionGambar::query()->whereKey($payload['question_id'])->value('title'));
        }

        if (isset($payload['game_id']) && is_string($payload['game_id'])) {
            return self::text(Game::query()->whereKey($payload['game_id'])->value('title'));
        }

        // A released claim names the list entry the host changed, as on the Names page.
        if (isset($payload['person_id']) && is_string($payload['person_id'])) {
            return self::text(Person::query()->whereKey($payload['person_id'])->value('name'));
        }

        if (isset($payload['account_id']) && is_string($payload['account_id'])) {
            return self::text(User::query()->whereKey($payload['account_id'])->value('name'));
        }

        // Deleted names and games are gone from their tables; the entry kept what they were called.
        $kept = $payload['name'] ?? $payload['title'] ?? null;

        if (is_string($kept)) {
            return $kept;
        }

        if (isset($payload['from'], $payload['to']) && is_string($payload['from']) && is_string($payload['to'])) {
            return "/{$payload['from']} → /{$payload['to']}";
        }

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
