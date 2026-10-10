<?php

declare(strict_types=1);

namespace App\Http\Requests\Join;

use App\Models\Event;
use App\Models\Person;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use LogicException;

/**
 * F3: the name picked on the phone must be on this event's list (B-2 allows one's own).
 */
final class VoteRequest extends FormRequest
{
    /** Audit CODE-2: the person the rule found, so the vote does not read it again. */
    private ?Person $found = null;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'target' => ['bail', 'required', 'uuid', function (string $attribute, mixed $value, Closure $fail) use ($event): void {
                $this->found = is_string($value) && $event instanceof Event
                    ? Person::query()->whereKey($value)->where('event_id', $event->id)->first()
                    : null;

                if ($this->found === null) {
                    $fail('validation.exists')->translate(['attribute' => $attribute]);
                }
            }],
            'attempt' => ['required', 'integer', 'min:1'],
        ];
    }

    public function target(): Person
    {
        return $this->found ?? throw new LogicException('The vote target is read after validation.');
    }
}
