<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\LoggedAction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E13: log of irreversible actions. K5: payload never holds participant names or tokens.
 */
#[Fillable(['event_id', 'user_id', 'action', 'payload'])]
final class ActionLog extends Model
{
    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'action' => LoggedAction::class,
            'payload' => 'array',
        ];
    }
}
