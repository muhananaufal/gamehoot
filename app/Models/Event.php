<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EventStatus;
use App\Enums\ScreenTheme;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['name', 'slug', 'show_on_devices', 'screen_theme'])]
final class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUuids, SoftDeletes;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => 'draft',
        'show_on_devices' => false,
        'screen_theme' => 'dark',
        'state_version' => 0,
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * C-2: co-hosts, stored in event_hosts.
     *
     * @return BelongsToMany<User, $this>
     */
    public function cohosts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_hosts')->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }

    /**
     * @return HasMany<Person, $this>
     */
    public function people(): HasMany
    {
        return $this->hasMany(Person::class);
    }

    /**
     * @return HasMany<Game, $this>
     */
    public function games(): HasMany
    {
        return $this->hasMany(Game::class);
    }

    /**
     * G7: the only marker of the active game.
     *
     * @return BelongsTo<Game, $this>
     */
    public function activeGame(): BelongsTo
    {
        return $this->belongsTo(Game::class, 'active_game_id');
    }

    public function isOwnedBy(User $user): bool
    {
        return $this->owner_id === $user->id;
    }

    public function isHostedBy(User $user): bool
    {
        return $this->isOwnedBy($user) || $this->cohosts()->whereKey($user->id)->exists();
    }

    /**
     * F15: every change clients can see bumps the version carried by snapshots.
     * Callers save the model inside their transaction.
     */
    public function bumpStateVersion(): void
    {
        $this->state_version++;
    }

    /**
     * D-3: the link a deleted event had, before it was freed.
     */
    public function originalSlug(): string
    {
        return (string) preg_replace('/--deleted-[0-9a-f-]{36}$/', '', $this->slug);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'screen_theme' => ScreenTheme::class,
            'show_on_devices' => 'boolean',
            'names_locked_at' => 'datetime',
            'join_locked_at' => 'datetime',
            'state_version' => 'integer',
        ];
    }
}
