<?php

declare(strict_types=1);

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Pest\Browser\Api\Webpage;
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

/*
| W11, P6: wait for a condition, not for a fixed time. Every page call of the browser plugin
| (assertSee, assertScript, ...) is retried until it passes or the timeout ends, so a fixed
| wait() before one only costs time. Fixed waits stay where the test measures something that
| moves (an animation, a timer).
*/

/**
 * The page ran its scripts: Alpine and its stores exist (resources/js/app.js starts Alpine last).
 * A function, not a constant, so static analysis finds it from every test file.
 */
function pageReady(): string
{
    return 'document.readyState === "complete" && !! window.Alpine';
}

/**
 * Waits until $done is true, checking every 0.1 s for at most $seconds, e.g. for what a click
 * saved on the server. Returns quietly on timeout: the assertion that follows reports the failure.
 *
 * @param  Closure(): bool  $done
 */
function until(AwaitableWebpage|PendingAwaitablePage|Webpage $page, Closure $done, float $seconds = 10.0): void
{
    $deadline = microtime(true) + $seconds;

    while (! $done() && microtime(true) < $deadline) {
        $page->wait(0.1);
    }
}

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
