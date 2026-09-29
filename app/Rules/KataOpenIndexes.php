<?php

declare(strict_types=1);

namespace App\Rules;

use App\Games\TebakKata\AnswerBoxes;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Boxes opened from the start must exist in the answer (E5), and at least one box stays closed.
 */
final class KataOpenIndexes implements DataAwareRule, ValidationRule
{
    /**
     * @var array<array-key, mixed>
     */
    private array $data = [];

    public function __construct(private readonly string $answerField) {}

    /**
     * @param  array<array-key, mixed>  $data
     */
    public function setData(array $data): self
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $answer = $this->data[$this->answerField] ?? null;

        // The answer field reports its own error; there is nothing to compare against.
        if (! is_array($value) || ! is_string($answer) || ! AnswerBoxes::supports($answer)) {
            return;
        }

        $count = AnswerBoxes::fromAnswer($answer)->count;

        $indexes = [];

        foreach ($value as $index) {
            $index = filter_var($index, FILTER_VALIDATE_INT);

            if ($index === false || $index < 0 || $index >= $count) {
                $fail('games.kata.open_index_out_of_range')->translate(['max' => $count - 1]);

                return;
            }

            $indexes[] = $index;
        }

        if (count(array_unique($indexes)) >= $count) {
            $fail('games.kata.all_boxes_open')->translate();
        }
    }
}
