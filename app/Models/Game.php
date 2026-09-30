<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\QuestionStatus;
use Database\Factories\GameFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A game session inside an event. Its questions are copies of a pack (D-9).
 */
#[Fillable(['type', 'title', 'position'])]
final class Game extends Model
{
    /** @use HasFactory<GameFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * G9: trace of the copied pack, never used to compute results.
     *
     * @return BelongsTo<QuestionPack, $this>
     */
    public function sourcePack(): BelongsTo
    {
        return $this->belongsTo(QuestionPack::class, 'source_pack_id')->withTrashed();
    }

    /**
     * @return HasMany<Question, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(Question::class);
    }

    /**
     * G7: the only marker of the question on screen.
     *
     * @return BelongsTo<Question, $this>
     */
    public function currentQuestion(): BelongsTo
    {
        return $this->belongsTo(Question::class, 'current_question_id');
    }

    /**
     * @return HasMany<GameResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(GameResult::class);
    }

    /**
     * D-9, T8: a game that ran is never reloaded or deleted, so its results stay. It ran when
     * it is finished, is the active game, or has a question that left its first status.
     */
    public function wasPlayed(Event $event, QuestionStatus $initialStatus): bool
    {
        return $this->status === GameStatus::Finished
            || $event->active_game_id === $this->id
            || $this->questions()->where('status', '!=', $initialStatus)->exists();
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => GameType::class,
            'status' => GameStatus::class,
            'position' => 'integer',
            'leaderboard_at' => 'immutable_datetime',
        ];
    }
}
