<?php

declare(strict_types=1);

namespace App\People;

use Normalizer;

/**
 * B-4: duplicate names are compared without case or extra spaces. The normalized form
 * is stored in people.name_normalized, which carries the unique index (G2).
 * B-8 (audit DATA-1): the index uses utf8mb4_unicode_ci, which also ignores accents, letter
 * width and ß/ss; the comparison folds the same way, so a clash is found before saving. Mirrors
 * normalizeName() in resources/js/name-review.js.
 */
final class PersonName
{
    /** The longest name people.name stores. */
    public const int MAX_LENGTH = 100;

    /** B-3, T9: the most names an event can have. */
    public const int MAX_PER_EVENT = 500;

    public static function display(string $name): string
    {
        $composed = Normalizer::normalize($name, Normalizer::FORM_C);

        return trim((string) preg_replace('/\s+/u', ' ', is_string($composed) ? $composed : $name));
    }

    public static function normalize(string $name): string
    {
        $display = self::display($name);
        // Compatibility decomposition turns full-width letters into plain ones and splits accents
        // off their letters; the accents (combining marks) are then dropped.
        $decomposed = Normalizer::normalize($display, Normalizer::FORM_KD);
        $unaccented = (string) preg_replace('/\p{Mn}+/u', '', is_string($decomposed) ? $decomposed : $display);
        // Case folding also turns ß into ss.
        $folded = mb_convert_case($unaccented, MB_CASE_FOLD, 'UTF-8');
        $composed = Normalizer::normalize($folded, Normalizer::FORM_C);

        return trim((string) preg_replace('/\s+/u', ' ', is_string($composed) ? $composed : $folded));
    }
}
