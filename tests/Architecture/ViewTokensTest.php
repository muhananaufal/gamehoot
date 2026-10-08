<?php

declare(strict_types=1);

/**
 * F21: views may only use semantic color tokens (bg-surface, text-ink, ...). Raw hex
 * values and Tailwind's default palette would bypass the dark theme.
 */
const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white';

/**
 * @return list<string>
 */
function colorViolations(string $contents): array
{
    $patterns = [
        '/#[0-9a-fA-F]{3,8}\b/',
        '/\b[a-z-]+-\[(?:#|rgb|hsl|oklch|color)/',
        '/\b(?:bg|text|border|ring|fill|stroke|outline|decoration|divide|placeholder|caret|accent|shadow|from|via|to)-(?:'.PALETTE.')(?:-\d{2,3})?\b/',
    ];

    $found = [];

    foreach ($patterns as $pattern) {
        preg_match_all($pattern, $contents, $matches);
        array_push($found, ...$matches[0]);
    }

    return $found;
}

it('finds raw colors in a view', function (string $contents, int $count): void {
    expect(colorViolations($contents))->toHaveCount($count);
})->with([
    'hex in a class' => ['<div class="bg-[#120D26]">', 2],
    'hex in style' => ['<div style="color: #fff">', 1],
    'default palette' => ['<p class="text-gray-900 bg-white border-red-500">', 3],
    'tokens only' => ['<div class="bg-surface text-ink border-line-strong fill-brand-yellow">', 0],
]);

it('uses semantic color tokens only', function (string $path): void {
    $contents = file_get_contents($path);

    expect($contents)->toBeString()
        ->and(colorViolations((string) $contents))->toBe([]);
})->with(bladeViews());

/**
 * U28: px sizes and clamp() still written by hand in a view, while the old views move to the
 * named sizes (text-caption ... text-display) step by step. A count may only go down; a new
 * hand-written size in any view fails, like a raw color (F21).
 */
const TYPE_SIZES_LEFT = [
    'auth/login.blade.php' => 2,
    'components/layouts/host.blade.php' => 4,
    'components/pack-list.blade.php' => 1,
    'host/events/live/tebak/tebak_kata.blade.php' => 1,
    'host/games/list.blade.php' => 1,
    'join/index.blade.php' => 1,
    'join/play.blade.php' => 5,
    'join/tebak/tebak_gambar.blade.php' => 1,
    'join/tebak/tebak_kata.blade.php' => 2,
    'screen/show.blade.php' => 28,
    'screen/tebak/tebak_kata.blade.php' => 1,
];

function handWrittenSizes(string $contents): int
{
    return (int) preg_match_all('/text-\[(?:[0-9.]+px|clamp\()/', $contents);
}

it('finds hand-written type sizes in a view (U28)', function (string $contents, int $count): void {
    expect(handWrittenSizes($contents))->toBe($count);
})->with([
    'px size' => ['<h1 class="text-[26px] lg:text-[30px]">', 2],
    'clamp' => ['<p class="text-[clamp(18px,2vw,34px)]">', 1],
    'named sizes and em' => ['<p class="text-body text-title-s text-[1.4em]">', 0],
]);

it('writes no new type size by hand (U28)', function (string $path): void {
    $root = dirname(__DIR__, 2).'/resources/views/';
    $view = strtr(substr($path, strlen($root)), DIRECTORY_SEPARATOR, '/');

    expect(handWrittenSizes((string) file_get_contents($path)))->toBeLessThanOrEqual(TYPE_SIZES_LEFT[$view] ?? 0);
})->with(bladeViews());
