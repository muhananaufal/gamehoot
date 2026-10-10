<?php

declare(strict_types=1);

namespace App\People;

use App\Models\Event;
use App\Models\Person;

/**
 * B-4: finds names that clash with an earlier row of the same list or with a name already
 * in the event. Comparison ignores case and extra spaces.
 */
final class DuplicateNames
{
    /**
     * @param  list<string>  $names
     * @return array<int, NameClash> keyed by list index
     */
    public static function in(array $names, ?Event $event): array
    {
        $existing = [];

        // U26: a new event has no names yet.
        foreach ($event?->people()->get(['name']) ?? [] as $person) {
            /** @var Person $person */
            // B-8: folded again from the name, since rows saved before B-8 kept their accents.
            $existing[PersonName::normalize($person->name)] = $person->name;
        }

        $seen = [];
        $problems = [];

        foreach ($names as $index => $name) {
            $key = PersonName::normalize($name);

            if (mb_strlen(PersonName::display($name)) > PersonName::MAX_LENGTH) {
                $problems[$index] = NameClash::tooLong();
            } elseif (isset($existing[$key])) {
                $problems[$index] = NameClash::withExisting($existing[$key]);
            } elseif (isset($seen[$key])) {
                $problems[$index] = NameClash::withRow($seen[$key] + 1, PersonName::display($name));
            } else {
                $seen[$key] = $index;
            }
        }

        return $problems;
    }
}
