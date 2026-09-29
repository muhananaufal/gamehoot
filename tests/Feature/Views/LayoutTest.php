<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

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

it('grows the logo eyes below 48 px so they stay visible (E19)', function (): void {
    expect(Blade::render('<x-logo :size="32" />'))->toContain('r="23"')
        ->and(Blade::render('<x-logo :size="96" />'))->toContain('r="21"');
});
