<?php

declare(strict_types=1);

namespace App\People;

use Normalizer;

/**
 * B-4: duplicate names are compared without case or extra spaces. The normalized form
 * is stored in people.name_normalized, which carries the unique index (G2).
 */
final class PersonName
{
    public static function display(string $name): string
    {
        $composed = Normalizer::normalize($name, Normalizer::FORM_C);

        return trim((string) preg_replace('/\s+/u', ' ', is_string($composed) ? $composed : $name));
    }

    public static function normalize(string $name): string
    {
        return mb_strtolower(self::display($name));
    }
}
