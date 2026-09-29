<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
final class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasUuids;

    /**
     * @return HasMany<Event, $this>
     */
    public function ownedEvents(): HasMany
    {
        return $this->hasMany(Event::class, 'owner_id');
    }

    /**
     * C-2: events where this user is a co-host.
     *
     * @return BelongsToMany<Event, $this>
     */
    public function cohostedEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_hosts')->withTimestamps();
    }

    /**
     * @return HasMany<QuestionPack, $this>
     */
    public function questionPacks(): HasMany
    {
        return $this->hasMany(QuestionPack::class, 'owner_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_super_admin' => 'boolean',
            'disabled_at' => 'datetime',
        ];
    }
}
