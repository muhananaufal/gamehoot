<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('makes the whole switch of a toggle clickable, not only its text (E14)', function (): void {
    $html = Blade::render('<x-toggle name="show_on_devices" label="Show questions" />');

    // The drawn switch is a label for the hidden checkbox, so a tap on it toggles.
    expect($html)->toMatch('/<label for="toggle-show_on_devices" class="[^"]*cursor-pointer[^"]*">\s*<input id="toggle-show_on_devices"/');
});

it('writes the score under each podium name with its unit (E17)', function (): void {
    $html = Blade::render('<x-podium stand="stand" :score="$score" />', ['score' => __('tebak.points')]);

    expect($html)->toContain('$store.realtime.count(')
        ->toContain('__N__ points')
        ->toContain('$store.realtime.initials(row.name)');
});

it('keeps link-style buttons 44 px tall and in the link color (O11, U11)', function (string $variant): void {
    $html = Blade::render('<x-button :variant="$variant">Copy</x-button>', ['variant' => $variant]);

    expect($html)->toContain('min-h-11')
        ->and($html)->not->toMatch('/\btext-accent\b/');
})->with(['link', 'link-destructive']);

it('hands the saved message of the last request to the toast area, read out politely (U3)', function (): void {
    session()->flash('status', 'Pack saved.');

    $html = Blade::render('<x-toasts />');

    expect($html)->toContain('aria-live="polite"')
        ->toContain('$store.toasts.push(')
        ->toContain('Pack saved.');
});

it('has no toast message when nothing was saved', function (): void {
    expect(Blade::render('<x-toasts />'))->toContain('aria-live="polite"')->not->toContain('$store.toasts.push(');
});
