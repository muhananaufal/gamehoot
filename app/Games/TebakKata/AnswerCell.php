<?php

declare(strict_types=1);

namespace App\Games\TebakKata;

/**
 * One character of a Tebak Kata answer. $box is the 0-based box number, or null for
 * punctuation that is always shown (E5).
 */
final readonly class AnswerCell
{
    public function __construct(
        public string $char,
        public ?int $box,
    ) {}
}
