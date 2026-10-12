<?php

declare(strict_types=1);

use App\Actions\Games\RunQuestionAction;
use App\Games\GameEngines;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

uses(RefreshDatabase::class);

/**
 * The measurements a page script returned, by name; anything that is not a number or a flag is null.
 *
 * @return array<string, bool|float|null>
 */
function measured(mixed $values): array
{
    return array_map(
        fn (mixed $value): bool|float|null => is_bool($value) ? $value : (is_numeric($value) ? (float) $value : null),
        is_array($values) ? $values : [],
    );
}

/*
| U34, U35 (audit FE-1, UI-1): the Public View cannot scroll, so what players need must fit the
| screen. Measured in the browser, like the audit did: the drawn size, not the class names.
*/

it('keeps the QR code and the joined count on screen with a long link at common projector sizes (U35)', function (int $width, int $height): void {
    // The longest link a host can choose (slug of 60 characters).
    $event = Pentahoot::event(['Rita Wulandari'], ['slug' => 'annual-company-gathering-and-awards-night-jakarta-2026-batch']);

    $page = visit("/{$event->slug}/screen")->resize($width, $height)->assertSee('Join at')->wait(1);

    $fit = measured($page->script(<<<'JS'
        (() => {
            const qr = document.querySelector('[data-test=join-qr]').getBoundingClientRect();
            const footer = document.querySelector('footer').getBoundingClientRect();
            const link = document.querySelector('[data-test=join-link]');
            return {
                qr: qr.bottom <= innerHeight && qr.right <= innerWidth,
                footer: footer.bottom <= innerHeight,
                page: document.documentElement.scrollHeight <= innerHeight + 1,
                linkPx: parseFloat(getComputedStyle(link).fontSize),
            };
        })()
        JS));

    expect($fit['qr'])->toBeTrue()
        ->and($fit['footer'])->toBeTrue()
        ->and($fit['page'])->toBeTrue()
        ->and($fit['linkPx'])->toBeGreaterThanOrEqual(24.0);
    $page->assertNoJavaScriptErrors();
})->with([
    'full HD' => [1920, 1080],
    'laptop 1366' => [1366, 768],
    '720p' => [1280, 720],
    '4:3' => [1024, 768],
]);

it('draws the countdown digits at about half of their ring, far bigger than its label (U34, UI-1)', function (): void {
    $event = Pentahoot::event();
    $question = Pentahoot::question(Pentahoot::running($event, ['Who is the most punctual?']));
    app(RunQuestionAction::class)->handle(app(GameEngines::class)->live($question->game()->firstOrFail()->type), 'start', $event->refresh(), $question, $event->owner()->firstOrFail());

    $page = visit("/{$event->slug}/screen")->resize(1920, 1080)->assertSee('Who is the most punctual?');

    $sizes = measured($page->script(<<<'JS'
        (() => {
            const timer = document.querySelector('[role=timer]');
            const digits = timer.querySelector('[data-test=countdown-digits]');
            const label = digits.nextElementSibling;
            return {
                ring: timer.getBoundingClientRect().height,
                digits: parseFloat(getComputedStyle(digits).fontSize),
                label: parseFloat(getComputedStyle(label).fontSize),
            };
        })()
        JS));

    // The audit measured 30 px digits in a 389 px ring, as small as the label.
    expect($sizes['digits'])->toBeGreaterThanOrEqual((float) $sizes['ring'] * 0.4)
        ->and($sizes['digits'])->toBeGreaterThanOrEqual(120.0)
        ->and($sizes['label'])->toBeGreaterThanOrEqual(36.0);
    $page->assertNoJavaScriptErrors();
});
