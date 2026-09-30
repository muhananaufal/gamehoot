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
use Carbon\CarbonImmutable;

/**
 * E13: the irreversible actions of an event, newest first: when, who, what, and on which
 * question, game or name. Shown to hosts only; K5 concerns the application log files.
 *
 * @phpstan-type Entry array{at: CarbonImmutable, actor: string, action: string, subject: ?string}
 */
final class EventActivity
{
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

        return null;
    }

    private static function text(mixed $value): ?string
    {
        return is_string($value) ? $value : null;
    }
}
