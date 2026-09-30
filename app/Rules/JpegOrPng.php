<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use SplFileInfo;
use Symfony\Component\Mime\MimeTypes;

/**
 * E8, O7: images are JPG or PNG, judged from the content, not the name. HEIC (iPhone photos)
 * gets its own message, since that is the file a host is most likely to pick by mistake.
 */
final class JpegOrPng implements ValidationRule
{
    public const array TYPES = ['image/jpeg', 'image/png'];

    private const array HEIC = ['image/heic', 'image/heif', 'image/heic-sequence', 'image/heif-sequence'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $type = $value instanceof SplFileInfo && $value->getPathname() !== ''
            ? MimeTypes::getDefault()->guessMimeType($value->getPathname())
            : null;

        if (in_array($type, self::HEIC, true)) {
            $fail('games.gambar.heic')->translate();
        } elseif (! in_array($type, self::TYPES, true)) {
            $fail('games.gambar.not_image')->translate();
        }
    }
}
