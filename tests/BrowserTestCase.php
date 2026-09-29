<?php

declare(strict_types=1);

namespace Tests;

use RuntimeException;

/**
 * W11: browser tests run the real CSS and JavaScript, so they need the Vite build
 * (npm run build) instead of the empty stand-in the other tests use.
 */
abstract class BrowserTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! is_file(public_path('build/manifest.json')) || is_file(public_path('hot'))) {
            throw new RuntimeException('Browser tests need built assets: run `npm run build` and stop the Vite dev server first.');
        }

        $this->withVite();
    }
}
