<?php

declare(strict_types=1);

use App\Enums\QuestionStatus;
use App\Enums\ReplayKind;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\Replay;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function replayLiveHtml(Event $event): string
{
    return (string) actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}")->assertOk()->getContent();
}

describe('the Replay card of Live control (D-11)', function (): void {
    it('lists the moments of each played game with a play button, the last finished game picked first', function (): void {
        $event = Pentahoot::event();
        Replay::pentahoot($event);
        $kata = Replay::kata($event);

        $html = replayLiveHtml($event);

        expect($html)->toContain('Replay on screen')
            ->and($html)->toContain('Q1 · Most punctual?')
            ->and($html)->toContain('Final podium')
            ->and($html)->toContain('Leaderboard after Q1')
            ->and($html)->toContain('No winner')
            ->and(substr_count($html, 'data-test="replay-show"'))->toBe(5)
            ->and($html)->toContain('action="'.route('host.events.replay.store', $event).'"')
            ->and($html)->toContain("'{$kata->id}'")
            // The same numbers as the steps, so games of the same pack can be told apart.
            ->and($html)->toContain('\u0022label\u0022:\u00222. Word Round\u0022')
            ->and($html)->toContain('\u0022label\u0022:\u00221. Office Awards\u0022');
    });

    it('marks the moment on screen and offers Back to live', function (): void {
        $event = Pentahoot::event();
        $kata = Replay::kata($event);
        $event->forceFill(['replay_kind' => ReplayKind::Final, 'replay_game_id' => $kata->id])->save();

        $html = replayLiveHtml($event);

        expect($html)->toContain('Showing: 1. Word Round · Final podium')
            ->and($html)->toContain('Back to live')
            ->and($html)->toContain('action="'.route('host.events.replay.destroy', $event).'"')
            ->and($html)->toContain('aria-current="true"');
    });

    it('turns the play buttons off while a question is on screen', function (): void {
        $event = Pentahoot::event();
        Replay::kata($event);
        $running = Pentahoot::running($event);
        $question = Pentahoot::question($running);
        $question->forceFill(['status' => QuestionStatus::Live])->save();
        $running->forceFill(['current_question_id' => $question->id])->save();

        $html = replayLiveHtml($event);

        expect($html)->toContain('Available between questions.')
            ->and(preg_match_all('/data-test="replay-show" disabled/', $html))->toBe(4);
    });

    it('leaves the card out until there is a moment, keeping the region that brings it in', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event);

        $html = replayLiveHtml($event);

        expect($html)->toContain('id="live-replay"')
            ->and($html)->not->toContain('Replay on screen');
    });
});

it('gives the Public View the replay layer (D-11)', function (): void {
    $event = Pentahoot::event();

    get(route('join.screen', $event))->assertOk()
        ->assertSee('x-data="replayScreen"', false)
        ->assertSee('data-test="replay"', false);
});
