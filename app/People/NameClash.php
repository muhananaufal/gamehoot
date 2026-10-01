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
        public string $name = '',
        public bool $tooLong = false,
    ) {}

    /**
     * B-4: longer than the name column takes (PersonName::MAX_LENGTH).
     */
    public static function tooLong(): self
    {
        return new self(null, null, '', true);
    }

    /**
     * $name is the clashing name itself, used in the example of how to tell the two apart.
     */
    public static function withRow(int $row, string $name): self
    {
        return new self($row, null, $name);
    }

    public static function withExisting(string $name): self
    {
        return new self(null, $name);
    }

    public function message(): string
    {
        if ($this->tooLong) {
            return __('people.too_long', ['max' => PersonName::MAX_LENGTH]);
        }

        return $this->existingName !== null
            ? __('people.duplicate_existing', ['name' => $this->existingName])
            : __('people.duplicate_row', ['row' => $this->row, 'name' => $this->name]);
    }
}
