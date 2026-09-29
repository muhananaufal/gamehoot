<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * G6: internal table with a bigint id. G2: one vote per person per question.
 */
#[Fillable(['question_id', 'voter_person_id', 'target_person_id', 'attempt'])]
final class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

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
    public function voter(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'voter_person_id');
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function target(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'target_person_id');
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
        ];
    }
}
