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
    'tokens only' => ['<div class="bg-surface text-ink border-field-line fill-brand-yellow">', 0],
]);

it('uses semantic color tokens only', function (string $path): void {
    $contents = file_get_contents($path);

    expect($contents)->toBeString()
        ->and(colorViolations((string) $contents))->toBe([]);
})->with(bladeViews());

/**
 * U28, U34 (audit UI-3, CODE-6): type sizes come from the named scale only (text-caption ...
 * text-display for the panel and phones, text-screen-* for the Public View). A size written by
 * hand (px, clamp) or one of Tailwind's own sizes (text-sm, text-xl, ...) fails, like a raw
 * color (F21). Sizes relative to the parent (text-[1.4em]) stay allowed.
 */
function handWrittenSizes(string $contents): int
{
    return (int) preg_match_all('/(?<![\w-])text-(?:\[(?:[0-9.]+px|clamp\(|min\(|max\()|(?:xs|sm|base|lg|xl|[2-9]xl)(?![\w-]))/', $contents);
}

it('finds hand-written and default type sizes (U28, U34)', function (string $contents, int $count): void {
    expect(handWrittenSizes($contents))->toBe($count);
})->with([
    'px size' => ['<h1 class="text-[26px] lg:text-[30px]">', 2],
    'clamp' => ['<p class="text-[clamp(18px,2vw,34px)]">', 1],
    'default sizes' => ['<p class="text-sm md:text-xl text-2xl">', 3],
    'named sizes and em' => ['<p class="text-body text-title-s text-screen-meta text-[1.4em] text-small-caps">', 0],
]);

it('writes every type size from the named scale (U28, U34)', function (string $path): void {
    expect(handWrittenSizes((string) file_get_contents($path)))->toBe(0);
})->with(bladeViews());

it('writes no hand-written type size in the class names of the scripts (U34)', function (): void {
    $found = 0;

    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/resources/js', FilesystemIterator::SKIP_DOTS)) as $file) {
        if ($file instanceof SplFileInfo && $file->getExtension() === 'js') {
            $found += handWrittenSizes((string) file_get_contents($file->getPathname()));
        }
    }

    expect($found)->toBe(0);
});
