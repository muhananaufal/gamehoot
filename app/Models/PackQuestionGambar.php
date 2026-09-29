<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'pack_question_gambar', key: 'pack_question_id', keyType: 'string', incrementing: false)]
#[Fillable(['title', 'answer_text'])]
final class PackQuestionGambar extends Model
{
    /**
     * @return BelongsTo<PackQuestion, $this>
     */
    public function packQuestion(): BelongsTo
    {
        return $this->belongsTo(PackQuestion::class);
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
}
