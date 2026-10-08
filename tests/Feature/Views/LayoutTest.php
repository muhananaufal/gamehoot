<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Str;

it('renders the auth layout with the theme script, title and logo', function (): void {
    $html = Blade::render('<x-layouts.auth title="Sign in">Form here</x-layouts.auth>');

    expect($html)
        ->toContain('<title>Sign in · Pentahoot</title>')
        ->toContain("localStorage.getItem('pentahoot-theme')")
        ->toContain('aria-label="Pentahoot"')
        ->toContain('Form here');
    expect($html)->not->toContain('data-theme="');
});

it('fixes the theme when a page asks for one and skips the theme script (F21)', function (): void {
    $html = Blade::render('<x-layouts.base title="Screen" theme="dark">Stage</x-layouts.base>');

    expect($html)->toMatch('/<html lang="en"\s+data-theme="dark"\s*>/');
    expect($html)->not->toContain('pentahoot-theme');
});

it('decides the motion mode in the head before first paint, from the viewer choice or the device (U27)', function (): void {
    $head = Str::before(Blade::render('<x-layouts.auth title="Sign in">Form here</x-layouts.auth>'), '</head>');

    expect($head)
        ->toContain("localStorage.getItem('pentahoot-motion')")
        ->toContain("matchMedia('(prefers-reduced-motion: reduce)')")
        ->toContain('document.documentElement.dataset.motion');
});

it('gives pages with a fixed theme the motion script too (U27)', function (): void {
    $head = Str::before(Blade::render('<x-layouts.base title="Screen" theme="dark">Stage</x-layouts.base>'), '</head>');

    expect($head)->toContain('document.documentElement.dataset.motion');
});

it('lets a page follow the device setting only, never a stored choice (U27, U31)', function (): void {
    $html = Blade::render('<x-layouts.base title="Screen" theme="dark" :remember-motion="false">Stage</x-layouts.base>');

    expect($html)
        ->toContain('data-motion-device-only')
        ->toContain('document.documentElement.dataset.motion');
    expect($html)->not->toContain('pentahoot-motion');
});

it('grows the logo eyes below 48 px so they stay visible (E19)', function (): void {
    expect(Blade::render('<x-logo :size="32" />'))->toContain('r="23"')
        ->and(Blade::render('<x-logo :size="96" />'))->toContain('r="21"');
});
