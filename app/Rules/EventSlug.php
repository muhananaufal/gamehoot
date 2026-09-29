<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * F10, D-3: the event link is one lowercase path segment that does not collide with app routes.
 */
final class EventSlug implements ValidationRule
{
    /** F10: first path segments used by the app itself. */
    public const array RESERVED = ['host', 'admin', 'login', 'logout', 'media', 'broadcasting', 'storage', 'build', 'up'];

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $value) !== 1 || strlen($value) < 3 || strlen($value) > 60) {
            $fail('events.slug_format')->translate();

            return;
        }

        if (in_array($value, self::RESERVED, true)) {
            $fail('events.slug_reserved')->translate();
        }
    }
}
