<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Accounts\FindAccount;
use App\Http\Concerns\LiveValidation;
use App\Models\Event;
use App\Models\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * C-2: add a co-host, or pick the new owner for a transfer. Either must be an active
 * host account other than the current owner. The two forms share the settings page, so
 * each posts its own field name to keep errors next to the right form.
 */
final class CohostRequest extends FormRequest
{
    use LiveValidation;

    private ?User $foundAccount = null;

    private bool $accountLookedUp = false;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            // C-6: a username or an email.
            $this->field() => ['required', 'string', function (string $attribute, mixed $value, Closure $fail): void {
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

    /**
     * Audit CODE-2: looked up once; the rule and the controller share the account.
     */
    public function account(): ?User
    {
        if (! $this->accountLookedUp) {
            $this->foundAccount = FindAccount::byLogin($this->string($this->field())->toString());
            $this->accountLookedUp = true;
        }

        return $this->foundAccount;
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
