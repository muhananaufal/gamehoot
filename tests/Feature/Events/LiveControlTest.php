<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/**
 * Live control of the event as its owner sees it.
 */
function liveHtml(Event $event): string
{
    return (string) actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}")->assertOk()->getContent();
}

describe('Live control in three columns (D-10)', function (): void {
    it('lists the steps from preparing to closing, and monitors the Public View', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event, title: 'Warm-up');
        Pentahoot::game($event, title: 'Office Awards');

        $html = liveHtml($event);

        expect($html)->toContain('aria-label="Steps"')
            ->and($html)->toContain('aria-label="Control"')
            ->and($html)->toContain('aria-label="Monitor"')
            ->and($html)->toMatch('#Prepare.*Open event.*1\. Warm-up.*2\. Office Awards.*Close event#s')
            ->and($html)->toContain('<iframe src="'.route('join.screen', $event).'"')
            ->and($html)->toContain(route('join.index', $event));
    });

    it('prepares a draft: names and games counted, then Open event', function (): void {
        $event = Pentahoot::event(attributes: ['status' => EventStatus::Draft]);
        Pentahoot::game($event);

        $html = liveHtml($event);

        expect($html)->toContain('data-phase="draft"')
            ->and($html)->toContain('Get ready')
            ->and($html)->toContain('3 names')
            ->and($html)->toContain('1 game')
            ->and($html)->toContain('action="'.route('host.events.open', $event).'"')
            ->and($html)->toContain('Players can join once the event is open');
    });

    it('offers the next game in order while the event is open', function (): void {
        $event = Pentahoot::event();
        $first = Pentahoot::game($event, title: 'Warm-up');
        $first->forceFill(['status' => GameStatus::Finished])->save();
        $event->forceFill(['active_game_id' => $first->id])->save();
        $next = Pentahoot::game($event, title: 'Office Awards');

        $html = liveHtml($event);

        expect($html)->toContain('data-phase="open"')
            ->and($html)->toContain('Next game')
            ->and($html)->toContain('action="'.route('host.events.games.start', [$event, $next]).'"')
            ->and($html)->toContain('action="'.route('host.events.close', $event).'"');
    });

    it('starts a later game out of order from the steps, after a confirmation (D-10)', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event, title: 'Warm-up');
        $later = Pentahoot::game($event, title: 'Team Trivia');

        $html = liveHtml($event);

        expect($html)->toContain('id="start-'.$later->id.'"')
            ->and($html)->toContain('Start “Team Trivia” now?')
            ->and($html)->toContain('“Team Trivia” starts before “Warm-up”.')
            ->and($html)->toContain('getElementById(&#039;start-'.$later->id.'&#039;).showModal()')
            ->and(substr_count($html, 'action="'.route('host.events.games.start', [$event, $later]).'"'))->toBe(1);
    });

    it('points to the Games page when the event has no game yet', function (): void {
        $event = Pentahoot::event();

        $html = liveHtml($event);

        expect($html)->toContain('data-phase="open"')
            ->and($html)->toContain('No games yet')
            ->and($html)->toContain('href="'.route('host.events.games.index', $event).'"');
    });

    it('runs the game and names it in the Close event dialog while playing', function (): void {
        $event = Pentahoot::event();
        Pentahoot::running($event);

        $html = liveHtml($event);

        expect($html)->toContain('data-phase="playing"')
            ->and($html)->toContain('Finish game')
            ->and($html)->toContain('“Office Awards” is still running. It ends first');
    });

    it('offers Close event and Add a game once every game is played', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);
        $game->forceFill(['status' => GameStatus::Finished])->save();
        $event->forceFill(['active_game_id' => $game->id])->save();

        $html = liveHtml($event);

        expect($html)->toContain('data-phase="all_played"')
            ->and($html)->toContain('All games played')
            ->and($html)->toContain('Add a game')
            ->and($html)->toContain('action="'.route('host.events.close', $event).'"');
    });

    it('links to the results and offers Reopen once the event is closed', function (): void {
        $event = Pentahoot::event(attributes: ['status' => EventStatus::Finished]);

        $html = liveHtml($event);

        expect($html)->toContain('data-phase="finished"')
            ->and($html)->toContain('Event closed')
            ->and($html)->toContain('href="'.route('host.events.results', $event).'"')
            ->and($html)->toContain('action="'.route('host.events.reopen', $event).'"');
    });

    it('keeps the running game, what comes next and the ways to end in one card (D-10)', function (): void {
        $event = Pentahoot::event();
        Pentahoot::running($event);
        Pentahoot::game($event, title: 'Team Trivia');

        $html = liveHtml($event);

        expect($html)->toMatch('#Up next: 2. Team Trivia.*Close event.*Close game#s')
            ->and($html)->toContain('Close “Office Awards” now? Questions that are not done yet will not count.')
            ->and($html)->not->toContain('→');
    });

    it('shows a closed event in yellow and links to its results without an arrow (D-10)', function (): void {
        $event = Pentahoot::event(attributes: ['status' => EventStatus::Finished]);

        $html = liveHtml($event);

        expect($html)->toMatch('#bg-warning[^"]*"[^>]*data-phase="finished"|data-phase="finished"[^>]*class="[^"]*bg-warning#')
            ->and($html)->toContain('See results')
            ->and($html)->not->toContain('→');
    });
});
