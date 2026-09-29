<?php

declare(strict_types=1);

use Tests\BrowserTestCase;
use Tests\TestCase;

/*
| Feature tests boot the Laravel application. Database state is reset per
| test file with RefreshDatabase where a test needs it (W13: MySQL, never SQLite).
*/
pest()->extend(TestCase::class)->in('Feature');
pest()->extend(BrowserTestCase::class)->in('Browser');

// W11: the first navigation of a run (browser launch, cold opcache) can take over 15 s when the
// code is read from a Windows mount (/mnt/c) in local Docker; CI is much faster.
pest()->browser()->timeout(30_000);

/**
 * @return array<string, array{string}>
 */
function bladeViews(): array
{
    $root = dirname(__DIR__).'/resources/views';
    $views = [];

    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($files as $file) {
        if ($file instanceof SplFileInfo && str_ends_with($file->getFilename(), '.blade.php')) {
            $views[substr($file->getPathname(), strlen($root) + 1)] = [$file->getPathname()];
        }
    }

    return $views;
}
