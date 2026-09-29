<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\GameType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * D-9: reusable question content owned by a host, for one game type.
 */
#[Fillable(['title', 'game_type'])]
final class QuestionPack extends Model
{
    use HasUuids, SoftDeletes;

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * @return HasMany<PackQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(PackQuestion::class, 'pack_id')->orderBy('position');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'game_type' => GameType::class,
        ];
    }
}
