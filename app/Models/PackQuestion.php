<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Columns shared by every game type. The type specific fields live in one detail table (F13).
 */
#[Fillable(['position', 'points'])]
final class PackQuestion extends Model
{
    use HasUuids;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'points' => 1,
    ];

    /**
     * @return BelongsTo<QuestionPack, $this>
     */
    public function pack(): BelongsTo
    {
        return $this->belongsTo(QuestionPack::class, 'pack_id');
    }

    /**
     * @return HasOne<PackQuestionPentahoot, $this>
     */
    public function pentahoot(): HasOne
    {
        return $this->hasOne(PackQuestionPentahoot::class);
    }

    /**
     * @return HasOne<PackQuestionKata, $this>
     */
    public function kata(): HasOne
    {
        return $this->hasOne(PackQuestionKata::class);
    }

    /**
     * @return HasOne<PackQuestionGambar, $this>
     */
    public function gambar(): HasOne
    {
        return $this->hasOne(PackQuestionGambar::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'points' => 'integer',
        ];
    }
}
