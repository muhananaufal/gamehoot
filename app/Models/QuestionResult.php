<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G10: Pentahoot top 5 frozen at Reveal, per attempt.
 */
#[Fillable(['question_id', 'attempt', 'person_id', 'rank', 'votes', 'frozen_at'])]
final class QuestionResult extends Model
{
    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
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
            'attempt' => 'integer',
            'rank' => 'integer',
            'votes' => 'integer',
            'frozen_at' => 'immutable_datetime',
        ];
    }
}
