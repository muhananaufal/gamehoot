<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * U24: every form sent to $action sits inside a dialog, so the page asks before it changes state.
 * Returns how many such forms the page has; a form outside a dialog fails the test.
 */
function formsInsideDialogs(string $html, string $action): int
{
    $needle = 'action="'.$action.'"';
    $count = 0;
    $offset = 0;

    while (($at = strpos($html, $needle, $offset)) !== false) {
        $before = substr($html, 0, $at);
        $opened = strrpos($before, '<dialog');
        $closed = strrpos($before, '</dialog>');

        expect($opened !== false && ($closed === false || $closed < $opened))
            ->toBeTrue("The form sent to {$action} is outside a dialog.");

        $count++;
        $offset = $at + strlen($needle);
    }

    return $count;
}

function hostPage(Event $event, string $path): string
{
    return (string) actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}{$path}")->assertOk()->getContent();
}

describe('changes of state ask first (U24)', function (): void {
    it('opens a draft event from Settings and from Live control only after a confirmation', function (): void {
        $event = Pentahoot::event(attributes: ['status' => EventStatus::Draft]);
        $open = route('host.events.open', $event);

        expect(formsInsideDialogs(hostPage($event, '/settings'), $open))->toBe(1)
            ->and(formsInsideDialogs(hostPage($event, ''), $open))->toBe(1);
    });

    it('reopens a finished event from Settings and from Live control only after a confirmation', function (): void {
        $event = Pentahoot::event(attributes: ['status' => EventStatus::Finished]);
        $reopen = route('host.events.reopen', $event);

        expect(formsInsideDialogs(hostPage($event, '/settings'), $reopen))->toBe(1)
            ->and(formsInsideDialogs(hostPage($event, ''), $reopen))->toBe(1);
    });

    it('locks and unlocks new name claims from Settings and Names only after a confirmation', function (): void {
        $event = Pentahoot::event();
        $event->forceFill(['names_locked_at' => now()])->save();
        $lock = route('host.events.join-lock', $event);

        expect(formsInsideDialogs(hostPage($event, '/settings'), $lock))->toBe(1)
            ->and(formsInsideDialogs(hostPage($event, '/people'), $lock))->toBe(1)
            ->and(hostPage($event, '/people'))->toContain('Lock claims?');

        $event->forceFill(['join_locked_at' => now()])->save();

        expect(hostPage($event, '/people'))->toContain('Unlock claims?')
            ->and(formsInsideDialogs(hostPage($event, '/people'), $lock))->toBe(1);
    });

    it('starts every game only after a confirmation, not just the first one', function (): void {
        $event = Pentahoot::event();
        $event->forceFill(['names_locked_at' => now()])->save();
        $game = Pentahoot::game($event, title: 'Office Awards');

        $html = hostPage($event, '');

        expect(formsInsideDialogs($html, route('host.events.games.start', [$event, $game])))->toBe(1)
            ->and($html)->toContain('Start “Office Awards”?');
    });

    it('skips a Tebak question only after a confirmation, as Skip uses its one skip', function (): void {
        $event = Pentahoot::event();
        TebakKata::running($event);

        $html = hostPage($event, '');

        expect($html)->toContain('id="skip-question"')
            ->and($html)->toContain('Skip this question?')
            ->and($html)->toContain("document.getElementById('skip-question').showModal()")
            ->and($html)->not->toContain("@click=\"act('skip')\"");
    });
});
