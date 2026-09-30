<?php

declare(strict_types=1);

namespace App\Games;

use App\Enums\Audience;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\MediaVisibility;
use App\Enums\QuestionStatus;
use App\Games\Tebak\TebakBoard;
use App\Games\TebakGambar\GambarActions;
use App\Media\MediaUrls;
use App\Models\Event;
use App\Models\Game;
use App\Models\MediaFile;
use App\Models\PackQuestion;
use App\Models\Question;
use App\Models\QuestionGambar;
use App\Models\User;
use App\Rules\JpegOrPng;
use Carbon\CarbonImmutable;
use Illuminate\Support\Arr;

/**
 * Tebak Gambar: a question image, then the answer image at Reveal (E9). The host's browser sends
 * every image in 1920 and 720 px (E8, E14); the upload itself is stored outside the engine.
 */
final readonly class TebakGambarEngine implements LiveGameEngine
{
    public function __construct(private GambarActions $actions, private TebakBoard $board, private MediaUrls $urls) {}

    public function type(): GameType
    {
        return GameType::TebakGambar;
    }

    public function imageFields(): array
    {
        // E9: the answer image stays private until Reveal.
        return ['question_image' => MediaVisibility::Public, 'answer_image' => MediaVisibility::Private];
    }

    public function questionRules(bool $creating): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            // Only hosts ever see the answer text; the room sees the answer image.
            'answer_text' => ['required', 'string', 'max:100'],
            // E16
            'points' => ['required', 'integer', 'between:0,2'],
            'question_image' => [$creating ? 'required' : 'nullable', ...self::imageRules(1920)],
            'question_image_small' => ['required_with:question_image', ...self::imageRules(720)],
            'answer_image' => [$creating ? 'required' : 'nullable', ...self::imageRules(1920)],
            'answer_image_small' => ['required_with:answer_image', ...self::imageRules(720)],
        ];
    }

    public function detailAttributes(array $validated): array
    {
        $attributes = Arr::only($validated, ['title', 'answer_text', 'question_image_id', 'answer_image_id']);

        if (isset($attributes['answer_text']) && is_string($attributes['answer_text'])) {
            $attributes['answer_text'] = trim($attributes['answer_text']);
        }

        return $attributes;
    }

    public function questionAttributes(array $validated): array
    {
        return Arr::only($validated, ['points']);
    }

    public function savePackDetail(PackQuestion $question, array $attributes): void
    {
        // The image ids come from the upload, never from the form, so they are set directly.
        $question->gambar()->firstOrNew()->forceFill($attributes)->save();
    }

    public function copyDetail(PackQuestion $source, Question $copy): void
    {
        $detail = $source->gambar()->firstOrFail();

        // D-9, G11: the copy points to the same image rows as the pack.
        $copy->gambar()->forceCreate([
            'title' => $detail->title,
            'answer_text' => $detail->answer_text,
            'question_image_id' => $detail->question_image_id,
            'answer_image_id' => $detail->answer_image_id,
        ]);
    }

    public function saveCopyDetail(Question $question, array $attributes): void
    {
        $question->gambar()->firstOrFail()->forceFill($attributes)->save();
    }

    public function initialStatus(): QuestionStatus
    {
        return QuestionStatus::Queued;
    }

    /**
     * F1, F2, F8, E9: the question on screen. The answer image is only in the public snapshot once
     * revealed or once the question is resolved; the answer text only ever goes to hosts.
     */
    public function snapshot(Game $game, Audience $audience): array
    {
        // UUIDv7 ids follow the order the questions were copied in, which a Skip does not change.
        $questions = $game->questions()->orderBy('id')->get(['id', 'position', 'status', 'skip_used']);
        $current = $game->currentQuestion()->with(['gambar.questionImage.variants', 'gambar.answerImage.variants', 'winner:id,name'])->first();
        $resolved = $questions->filter(fn (Question $question): bool => TebakBoard::resolved($question))->count();

        $snapshot = [
            'id' => $game->id,
            'type' => $game->type->value,
            'title' => $game->title,
            'status' => $game->status->value,
            'count' => $questions->count(),
            'question' => $current === null ? null : $this->questionPart($current, $audience, $resolved),
            'leaderboard' => $game->leaderboard_at === null ? null : $this->board->live($game),
            'final' => $game->status === GameStatus::Finished ? $this->board->frozen($game) : null,
        ];

        if ($audience === Audience::Host) {
            $snapshot['statuses'] = TebakBoard::statusCodes($questions);
            $snapshot['live_board'] = $this->board->live($game);
        }

        return $snapshot;
    }

    public function liveCounters(Game $game, Audience $audience): array
    {
        return [];
    }

    public function actions(): array
    {
        return GambarActions::ACTIONS;
    }

    public function actionRules(string $action): array
    {
        return GambarActions::rules($action);
    }

    public function perform(string $action, Event $event, Question $question, User $actor, array $input): void
    {
        $this->actions->perform($action, $event, $question, $actor, $input);
    }

    public function finish(Game $game, CarbonImmutable $at): void
    {
        $this->board->freeze($game, $at);
    }

    /**
     * D-2: once shown (or skipped after being shown) a question is locked.
     */
    public function wasShown(Question $question): bool
    {
        return $question->status !== QuestionStatus::Queued || $question->skip_used;
    }

    /**
     * @return list<mixed>
     */
    private static function imageRules(int $maxWidth): array
    {
        // E8, K7: 2 MB after the browser resized the image to at most $maxWidth px.
        return ['file', new JpegOrPng, 'max:2048', "dimensions:max_width={$maxWidth}"];
    }

    /**
     * @return array<string, mixed>
     */
    private function questionPart(Question $question, Audience $audience, int $resolvedCount): array
    {
        $detail = $question->gambar;

        if (! $detail instanceof QuestionGambar || ! $detail->questionImage instanceof MediaFile || ! $detail->answerImage instanceof MediaFile) {
            return [];
        }

        $resolved = TebakBoard::resolved($question);
        $revealed = $resolved || $detail->revealed_at !== null;

        $part = [
            'id' => $question->id,
            'number' => $resolved ? $resolvedCount : $resolvedCount + 1,
            'title' => $detail->title,
            'status' => $question->status->value,
            'points' => $question->points,
            'skipped' => $question->skip_used,
            'revealed' => $revealed,
            'image' => $this->urls->sizes($detail->questionImage),
            'answer_image' => $revealed ? $this->urls->sizes($detail->answerImage) : null,
            'winner' => $question->winner->name ?? null,
        ];

        if ($audience === Audience::Host) {
            // Hosts see the answer, text and image, from the start (E9).
            $part['answer'] = $detail->answer_text;
            $part['answer_image'] = $this->urls->sizes($detail->answerImage);
        }

        return $part;
    }
}
