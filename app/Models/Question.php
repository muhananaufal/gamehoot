<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\QuestionStatus;
use Database\Factories\QuestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A copy of a pack question inside a game (D-9). Status transitions belong to the game engine (F13).
 */
#[Fillable(['position', 'points', 'status'])]
final class Question extends Model
{
    /** @use HasFactory<QuestionFactory> */
    use HasFactory, HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'points' => 1,
        'skip_used' => false,
    ];

    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * G9: trace of the copied pack question, never used to compute results.
     *
     * @return BelongsTo<PackQuestion, $this>
     */
    public function sourcePackQuestion(): BelongsTo
    {
        return $this->belongsTo(PackQuestion::class, 'source_pack_question_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function winner(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'winner_person_id');
    }

    /**
     * @return HasOne<QuestionPentahoot, $this>
     */
    public function pentahoot(): HasOne
    {
        return $this->hasOne(QuestionPentahoot::class);
    }

    /**
     * @return HasOne<QuestionKata, $this>
     */
    public function kata(): HasOne
    {
        return $this->hasOne(QuestionKata::class);
    }

    /**
     * @return HasOne<QuestionGambar, $this>
     */
    public function gambar(): HasOne
    {
        return $this->hasOne(QuestionGambar::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votes(): HasMany
    {
        return $this->hasMany(Vote::class);
    }

    /**
     * @return HasMany<QuestionResult, $this>
     */
    public function results(): HasMany
    {
        return $this->hasMany(QuestionResult::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => QuestionStatus::class,
            'position' => 'integer',
            'points' => 'integer',
            'skip_used' => 'boolean',
            'answer_revealed_at' => 'datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
