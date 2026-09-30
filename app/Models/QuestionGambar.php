<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'question_gambar', key: 'question_id', keyType: 'string', incrementing: false)]
#[Fillable(['title', 'answer_text'])]
#[Hidden(['answer_text', 'answer_image_id'])]
final class QuestionGambar extends Model
{
    /**
     * @return BelongsTo<Question, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    /**
     * @return BelongsTo<MediaFile, $this>
     */
    public function questionImage(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'question_image_id');
    }

    /**
     * E9: private, only served through a temporary signed URL at Reveal.
     *
     * @return BelongsTo<MediaFile, $this>
     */
    public function answerImage(): BelongsTo
    {
        return $this->belongsTo(MediaFile::class, 'answer_image_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            // E7, E9: when the answer image was revealed.
            'revealed_at' => 'immutable_datetime',
        ];
    }
}
