<?php

declare(strict_types=1);

namespace App\Http\Requests\Host;

use App\Models\Event;
use App\Models\Person;
use App\People\PersonName;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

/**
 * B-4: add or rename one name. T9: at most 500 names per event.
 */
final class PersonNameRequest extends FormRequest
{
    public const int MAX_NAMES = 500;

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', function (string $attribute, mixed $value, Closure $fail): void {
                $event = $this->route('event');
                $person = $this->route('person');

                if (! $event instanceof Event || ! is_string($value)) {
                    return;
                }

                if (PersonName::display($value) === '') {
                    $fail('validation.required')->translate(['attribute' => $attribute]);

                    return;
                }

                $clash = $event->people()
                    ->where('name_normalized', PersonName::normalize($value))
                    ->when($person instanceof Person, fn ($query) => $query->whereKeyNot($person instanceof Person ? $person->id : null))
                    ->value('name');

                if (is_string($clash)) {
                    $fail('people.duplicate_existing')->translate(['name' => $clash]);
                } elseif (! $person instanceof Person && $event->people()->count() >= self::MAX_NAMES) {
                    $fail('people.too_many')->translate(['max' => self::MAX_NAMES]);
                }
            }],
        ];
    }
}
