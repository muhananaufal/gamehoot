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
use Illuminate\Database\Eloquent\Model;

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

        // Audit PERF-5: the subjects are read once per kind for the whole log, not per entry.
        $ids = fn (string $key): array => array_values(array_unique(array_filter(
            $logs->map(fn (ActionLog $log): mixed => is_array($log->payload) ? ($log->payload[$key] ?? null) : null)->all(),
            is_string(...),
        )));
        $questionIds = $ids('question_id');
        // A question has one of the three detail rows; the first kind found wins, as before.
        $names = [
            'question_id' => self::column(QuestionPentahoot::class, 'prompt', $questionIds)
                + self::column(QuestionKata::class, 'prompt', $questionIds)
                + self::column(QuestionGambar::class, 'title', $questionIds),
            'game_id' => self::column(Game::class, 'title', $ids('game_id')),
            'person_id' => self::column(Person::class, 'name', $ids('person_id')),
            'account_id' => self::column(User::class, 'name', $ids('account_id')),
        ];

        return array_values($logs->map(fn (ActionLog $log): array => [
            'at' => CarbonImmutable::instance($log->created_at ?? now()),
            // U4: entries without a user (the system) share one id for the Who filter.
            'actor_id' => $log->user_id ?? self::SYSTEM,
            'actor' => $log->user->name ?? __('logs.system'),
            'action' => __('logs.actions.'.str_replace('.', '_', $log->action->value)),
            'subject' => self::subject($log, $names),
        ])->all());
    }

    /**
     * One column of the rows with the given keys, by key.
     *
     * @param  class-string<Model>  $model
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    private static function column(string $model, string $column, array $keys): array
    {
        if ($keys === []) {
            return [];
        }

        $query = $model::query();
        $values = [];

        foreach ($query->whereKey($keys)->toBase()->pluck($column, $query->getModel()->getKeyName()) as $key => $value) {
            if (is_string($value)) {
                $values[(string) $key] = $value;
            }
        }

        return $values;
    }

    /**
     * @param  array<string, array<string, string>>  $names
     */
    private static function subject(ActionLog $log, array $names): ?string
    {
        $payload = is_array($log->payload) ? $log->payload : [];
        $named = fn (string $key): ?string => is_string($payload[$key]) ? ($names[$key][$payload[$key]] ?? null) : null;

        if (isset($payload['question_id']) && is_string($payload['question_id'])) {
            return $named('question_id');
        }

        // E13: added names keep their count only.
        if (isset($payload['count']) && is_int($payload['count'])) {
            return trans_choice('logs.names_count', $payload['count']);
        }

        $kept = $payload['name'] ?? $payload['title'] ?? null;

        if (isset($payload['game_id']) && is_string($payload['game_id'])) {
            return $named('game_id');
        }

        // A released claim names the list entry the host changed, as on the Names page.
        if (isset($payload['person_id']) && is_string($payload['person_id'])) {
            return $named('person_id');
        }

        if (isset($payload['account_id']) && is_string($payload['account_id'])) {
            return $named('account_id');
        }

        // Deleted names and games are gone from their tables; the entry kept what they were called.
        if (is_string($kept)) {
            return $kept;
        }

        if (isset($payload['from'], $payload['to']) && is_string($payload['from']) && is_string($payload['to'])) {
            return "/{$payload['from']} → /{$payload['to']}";
        }

        return null;
    }
}
