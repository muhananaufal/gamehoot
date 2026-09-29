<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'question_pentahoot', key: 'question_id', keyType: 'string', incrementing: false)]
#[Fillable(['prompt', 'duration_seconds'])]
final class QuestionPentahoot extends Model
{
    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'attempt' => 1,
    ];

    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'duration_seconds' => 'integer',
            'attempt' => 'integer',
            'ends_at' => 'immutable_datetime',
        ];
    }
}
