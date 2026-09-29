<?php

declare(strict_types=1);

/**
 * Blade directives such as @js are not compiled inside the attributes of a component tag
 * (<x-...>); they reach the browser as raw text and break the Alpine expression.
 *
 * @return list<string>
 */
function componentTagsWithDirectives(string $contents): array
{
    preg_match_all('/<x-[\w.:-]+(?:[^>"]|"[^"]*")*>/s', $contents, $tags);

    return array_values(array_filter(
        $tags[0],
        fn (string $tag): bool => preg_match('/@(js|json|class|style|checked|selected|disabled)\s*\(/', $tag) === 1,
    ));
}

it('finds a directive inside a component tag', function (): void {
    expect(componentTagsWithDirectives('<x-chip x-text="@js($a)">A</x-chip>'))->toHaveCount(1)
        ->and(componentTagsWithDirectives('<span x-text="@js($a)"></span><x-chip tone="success">A</x-chip>'))->toBe([]);
});

it('keeps Blade directives out of component tag attributes', function (string $path): void {
    expect(componentTagsWithDirectives((string) file_get_contents($path)))->toBe([]);
})->with(bladeViews());
