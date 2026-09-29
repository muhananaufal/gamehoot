<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A name on the master list of an event (B-3).
 */
#[Fillable(['name', 'name_normalized'])]
#[Hidden(['join_token', 'claim_token_hash'])]
final class Person extends Model
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory, HasUuids;

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votesCast(): HasMany
    {
        return $this->hasMany(Vote::class, 'voter_person_id');
    }

    /**
     * @return HasMany<Vote, $this>
     */
    public function votesReceived(): HasMany
    {
        return $this->hasMany(Vote::class, 'target_person_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'claimed_at' => 'datetime',
        ];
    }
}
