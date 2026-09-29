<?php

declare(strict_types=1);

namespace App\People;

/**
 * B-4: why a name cannot be saved: it repeats an earlier row of the list, or a name
 * already in the event.
 */
final readonly class NameClash
{
    private function __construct(
        public ?int $row,
        public ?string $existingName,
    ) {}

    public static function withRow(int $row): self
    {
        return new self($row, null);
    }

    public static function withExisting(string $name): self
    {
        return new self(null, $name);
    }

    public function message(): string
    {
        return $this->existingName !== null
            ? __('people.duplicate_existing', ['name' => $this->existingName])
            : __('people.duplicate_row', ['row' => $this->row]);
    }
}
