<?php

declare(strict_types=1);

namespace App\Games\TebakGambar;

use App\Enums\QuestionStatus;
use App\Exceptions\ActionRefused;
use App\Games\Tebak\TebakActions;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\QuestionGambar;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * The host actions of a Tebak Gambar question: the shared Tebak actions plus Reveal of the answer
 * image (E9). E7: Skip is off once the answer is revealed; Pick Winner and Surrender stay.
 */
final readonly class GambarActions
{
    public const array ACTIONS = [...TebakActions::ACTIONS, 'reveal'];

    public function __construct(private TebakActions $tebak) {}

    /**
     * @return array<string, mixed>
     */
    public static function rules(string $action): array
    {
        return TebakActions::rules($action);
    }

    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ActionRefused
     */
    public function perform(string $action, Event $event, Question $question, User $actor, array $input): void
    {
        [$game, $locked] = $this->tebak->lock($event, $question);
        $detail = QuestionGambar::query()->lockForUpdate()->findOrFail($question->id);

        match ($action) {
            'reveal' => $this->reveal($game, $locked, $detail),
            'skip' => $detail->revealed_at === null
                ? $this->tebak->perform($action, $event, $game, $locked, $actor, $input)
                : throw ActionRefused::stale(),
            default => $this->tebak->perform($action, $event, $game, $locked, $actor, $input),
        };
    }

    /**
     * E9: once, while the question is on screen and still open.
     */
    private function reveal(Game $game, Question $question, QuestionGambar $detail): void
    {
        $this->tebak->expectCurrent($game, $question, QuestionStatus::Shown);

        if ($detail->revealed_at !== null) {
            throw ActionRefused::stale();
        }

        $detail->forceFill(['revealed_at' => CarbonImmutable::now()])->save();
    }
}
