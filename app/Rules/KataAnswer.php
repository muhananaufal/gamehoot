<?php

declare(strict_types=1);

namespace App\Rules;

use App\Games\TebakKata\AnswerBoxes;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * E5: the answer must be shown as letter boxes and allowed punctuation only.
 */
final class KataAnswer implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! AnswerBoxes::supports($value)) {
            $fail('games.kata.answer_unsupported')->translate();
        }
    }
}
