<?php

declare(strict_types=1);

namespace App\Games\TebakKata;

use App\Exceptions\UnsupportedKataAnswer;
use Normalizer;

/**
 * E5: splits a Tebak Kata answer into boxes.
 *
 * - Every letter and digit is one box, numbered from 0 across the whole answer.
 * - The punctuation - ' . / & is shown as is and is not a box.
 * - Letters are shown in capitals; words are split on whitespace so long answers
 *   wrap per word.
 */
final readonly class AnswerBoxes
{
    private const array PUNCTUATION = ['-', "'", '.', '/', '&'];

    /** Typographic variants typed by phone and office keyboards. */
    private const array PUNCTUATION_ALIASES = ['’' => "'", '‘' => "'", '–' => '-'];

    /**
     * @param  list<list<AnswerCell>>  $words
     */
    private function __construct(
        public array $words,
        public int $count,
    ) {}

    /**
     * @throws UnsupportedKataAnswer
     */
    public static function fromAnswer(string $answer): self
    {
        $normalized = Normalizer::normalize($answer, Normalizer::FORM_C);
        $text = mb_strtoupper(strtr(is_string($normalized) ? $normalized : $answer, self::PUNCTUATION_ALIASES));

        $words = [];
        $count = 0;

        foreach (preg_split('/ +/u', trim($text, ' '), -1, PREG_SPLIT_NO_EMPTY) ?: [] as $word) {
            $cells = [];

            foreach (mb_str_split($word) as $char) {
                if (preg_match('/^[\p{L}\p{N}]$/u', $char) === 1) {
                    $cells[] = new AnswerCell($char, $count++);
                } elseif (in_array($char, self::PUNCTUATION, true)) {
                    $cells[] = new AnswerCell($char, null);
                } else {
                    throw UnsupportedKataAnswer::character($char);
                }
            }

            $words[] = $cells;
        }

        if ($count === 0) {
            throw UnsupportedKataAnswer::empty();
        }

        return new self($words, $count);
    }

    public static function supports(string $answer): bool
    {
        try {
            self::fromAnswer($answer);
        } catch (UnsupportedKataAnswer) {
            return false;
        }

        return true;
    }
}
