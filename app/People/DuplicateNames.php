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
    public static function in(array $names, Event $event): array
    {
        $existing = [];

        foreach ($event->people()->get(['name', 'name_normalized']) as $person) {
            /** @var Person $person */
            $existing[$person->name_normalized] = $person->name;
        }

        $seen = [];
        $problems = [];

        foreach ($names as $index => $name) {
            $key = PersonName::normalize($name);

            if (isset($existing[$key])) {
                $problems[$index] = NameClash::withExisting($existing[$key]);
            } elseif (isset($seen[$key])) {
                $problems[$index] = NameClash::withRow($seen[$key] + 1);
            } else {
                $seen[$key] = $index;
            }
        }

        return $problems;
    }
}
