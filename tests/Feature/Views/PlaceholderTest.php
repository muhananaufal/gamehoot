<?php

declare(strict_types=1);

use Symfony\Component\Finder\Finder;

/**
 * U25: every text input and text area shows an example as its placeholder. Inputs a person never
 * types in (hidden, checkbox, radio, file) are left out.
 */
it('gives every text input and text area a placeholder (U25)', function (): void {
    $missing = [];

    foreach (Finder::create()->files()->in(resource_path('views'))->name('*.blade.php') as $file) {
        $source = $file->getContents();

        // A tag runs to its first ">" that is not inside a quoted attribute.
        preg_match_all('/<(x-field|input|textarea)\b((?:[^>"\']|"[^"]*"|\'[^\']*\')*)>/', $source, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

        foreach ($tags as $tag) {
            $attributes = $tag[2][0];

            // The input inside x-field gets its placeholder through $attributes.
            if (preg_match('/\btype="(hidden|checkbox|radio|file)"/', $attributes) === 1
                || str_contains($attributes, 'placeholder') || str_contains($attributes, '$attributes')) {
                continue;
            }

            $line = substr_count(substr($source, 0, $tag[0][1]), "\n") + 1;
            $missing[] = $file->getRelativePathname().':'.$line;
        }
    }

    expect($missing)->toBe([]);
});
