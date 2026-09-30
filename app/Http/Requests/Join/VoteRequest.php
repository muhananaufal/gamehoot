<?php

declare(strict_types=1);

namespace App\Http\Requests\Join;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * F3: the name picked on the phone must be on this event's list (B-2 allows one's own).
 */
final class VoteRequest extends FormRequest
{
    /**
     * @return array<string, list<string|Exists>>
     */
    public function rules(): array
    {
        $event = $this->route('event');

        return [
            'target' => ['required', 'uuid', Rule::exists('people', 'id')->where('event_id', $event instanceof Event ? $event->id : null)],
            'attempt' => ['required', 'integer', 'min:1'],
        ];
    }

    public function target(): Person
    {
        return Person::query()->findOrFail($this->string('target')->toString());
    }
}
