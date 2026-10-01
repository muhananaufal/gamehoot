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
