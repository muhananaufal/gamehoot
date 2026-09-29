<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'question_kata', key: 'question_id', keyType: 'string', incrementing: false)]
#[Fillable(['prompt', 'answer_text', 'initial_open_indexes'])]
#[Hidden(['answer_text'])]
final class QuestionKata extends Model
{
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
            'initial_open_indexes' => 'array',
            'opened_indexes' => 'array',
        ];
    }
}
