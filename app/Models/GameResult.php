<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G10: final Tebak leaderboard frozen when the game finishes.
 */
#[Fillable(['game_id', 'person_id', 'rank', 'points', 'reached_at', 'frozen_at'])]
final class GameResult extends Model
{
    /**
     * @return BelongsTo<Game, $this>
     */
    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rank' => 'integer',
            'points' => 'integer',
            'reached_at' => 'immutable_datetime',
            'frozen_at' => 'immutable_datetime',
        ];
    }
}
