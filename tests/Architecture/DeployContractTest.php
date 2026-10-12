<?php

declare(strict_types=1);

/**
 * P9 (audit OPS-1): what Reverb needs for a full venue is part of the images and the production
 * compose file, not something remembered on deploy day.
 */
it('builds every PHP image with the libuv event loop for Reverb (P9)', function (): void {
    $dockerfile = (string) file_get_contents(dirname(__DIR__, 2).'/Dockerfile');
    $base = (string) strstr((string) strstr($dockerfile, 'AS base'), 'AS dev', true);

    expect($base)->toMatch('/install-php-extensions uv-[0-9.]+/');
});

it('lets the production Reverb container keep thousands of sockets open (P9)', function (): void {
    $compose = (string) file_get_contents(dirname(__DIR__, 2).'/deploy/compose.yaml');
    $reverb = (string) strstr((string) strstr($compose, "\n  reverb:"), "\n  backup:", true);

    expect($reverb)->toContain('ulimits:')
        ->toMatch('/nofile:\s+soft: (\d{5,})\s+hard: \d{5,}/');
});
