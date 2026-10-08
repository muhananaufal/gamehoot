<?php

declare(strict_types=1);

namespace App\People;

use App\Models\Event;

/**
 * B-4, U26: names typed one per line (Add names, the New event names step). Problems name the
 * line the host sees, empty lines counted, like the page does (name-batch.js).
 */
final readonly class NameLines
{
    /**
     * @param  array<int, string>  $lines  the names keyed by their line (from 1), empty lines left out
     */
    private function __construct(public array $lines) {}

    public static function parse(string $text): self
    {
        $lines = [];

        foreach (preg_split('/\r\n|\n|\r/', $text) ?: [] as $index => $typed) {
            $name = PersonName::display($typed);

            if ($name !== '') {
                $lines[$index + 1] = $name;
            }
        }

        return new self($lines);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_values($this->lines);
    }

    /**
     * What stops these names from being saved: clashes with the event's names (none for a new
     * event), repeats, names over 100 characters, and more than 500 names in all (B-3).
     *
     * @return list<string>
     */
    public function problems(?Event $event): array
    {
        $names = $this->names();
        $lineOf = array_keys($this->lines);
        $problems = [];

        foreach (DuplicateNames::in($names, $event) as $index => $clash) {
            $problems[] = __('people.on_line', ['line' => $lineOf[$index], 'problem' => $clash->lineMessage($lineOf)]);
        }

        if (($event?->people()->count() ?? 0) + count($names) > PersonName::MAX_PER_EVENT) {
            $problems[] = __('people.too_many', ['max' => PersonName::MAX_PER_EVENT]);
        }

        return $problems;
    }
}
