<?php

declare(strict_types=1);

/**
 * F21: views may only use semantic color tokens (bg-surface, text-ink, ...). Raw hex
 * values and Tailwind's default palette would bypass the dark theme.
 */
const PALETTE = 'slate|gray|zinc|neutral|stone|red|orange|amber|yellow|lime|green|emerald|teal|cyan|sky|blue|indigo|violet|purple|fuchsia|pink|rose|black|white';

/**
 * @return array<string, array{string}>
 */
function bladeViews(): array
{
    $root = dirname(__DIR__, 2).'/resources/views';
    $views = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
            $views[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
        }
    }

    return $views;
}

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
