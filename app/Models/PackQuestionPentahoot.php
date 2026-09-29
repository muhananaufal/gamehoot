<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Table(name: 'pack_question_pentahoot', key: 'pack_question_id', keyType: 'string', incrementing: false)]
#[Fillable(['prompt', 'duration_seconds'])]
final class PackQuestionPentahoot extends Model
{
    /**
     * @return BelongsTo<PackQuestion, $this>
     */
    public function packQuestion(): BelongsTo
    {
        return $this->belongsTo(PackQuestion::class);
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
        ];
    }
}
