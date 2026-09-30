<?php

declare(strict_types=1);

namespace App\Games\TebakKata;

use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Games\Tebak\TebakActions;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionKata;
use App\Models\User;

/**
 * The host actions of a Tebak Kata question: the shared Tebak actions plus a hint (E5).
 */
final readonly class KataActions
{
    public const array ACTIONS = [...TebakActions::ACTIONS, 'hint'];

    public function __construct(private TebakActions $tebak) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $action): array
    {
        return match ($action) {
            'hint' => ['box' => ['required', 'integer', 'min:0']],
            default => TebakActions::rules($action),
        };
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ActionRefused
     */
    public function perform(string $action, Event $event, Question $question, User $actor, array $input): void
    {
        [$game, $locked] = $this->tebak->lock($event, $question);
        $detail = QuestionKata::query()->lockForUpdate()->findOrFail($question->id);

        if ($action === 'hint') {
            $this->hint($game, $locked, $detail, is_numeric($input['box'] ?? null) ? (int) $input['box'] : -1);

            return;
        }

        $this->tebak->perform($action, $event, $game, $locked, $actor, $input);
    }

    /**
     * E5: a hint opens one closed box; the last closed box stays closed, like at the start.
     */
    private function hint(Game $game, Question $question, QuestionKata $detail, int $box): void
    {
        $this->tebak->expectCurrent($game, $question, QuestionStatus::Shown);

        $count = AnswerBoxes::fromAnswer($detail->answer_text)->count;
        $opened = $detail->opened_indexes;

        if ($box < 0 || $box >= $count || in_array($box, $opened, true) || count($opened) + 1 >= $count) {
            throw ActionRefused::stale();
        }

        $opened[] = $box;
        sort($opened);
        $detail->forceFill(['opened_indexes' => $opened])->save();
    }
}
