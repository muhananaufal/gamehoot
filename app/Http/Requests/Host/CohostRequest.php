<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * C-2: add a co-host, or pick the new owner for a transfer. Either must be an active
 * host account other than the current owner. The two forms share the settings page, so
 * each posts its own field name to keep errors next to the right form.
 */
final class CohostRequest extends FormRequest
{
    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            $this->field() => ['required', 'string', 'email', function (string $attribute, mixed $value, Closure $fail): void {
                $account = $this->account();
                $event = $this->event();

                if ($account === null || $account->disabled_at !== null) {
                    $fail('events.cohost_unknown')->translate();
                } elseif ($event->isOwnedBy($account)) {
                    $fail('events.cohost_is_owner')->translate();
                } elseif ($this->isAddingCohost() && $event->cohosts()->whereKey($account->id)->exists()) {
                    $fail('events.cohost_exists')->translate();
                }
            }],
        ];
    }

    public function account(): ?User
    {
        return User::query()->where('email', Str::lower($this->string($this->field())->trim()->toString()))->first();
    }

    public function event(): Event
    {
        $event = $this->route('event');

        if (! $event instanceof Event) {
            abort(404);
        }

        return $event;
    }

    private function isAddingCohost(): bool
    {
        return $this->routeIs('host.events.cohosts.store');
    }

    private function field(): string
    {
        return $this->isAddingCohost() ? 'email' : 'new_owner_email';
    }
}
