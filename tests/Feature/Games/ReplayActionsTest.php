<?php

declare(strict_types=1);

use App\Actions\Games\FinishGame;
use App\Actions\Games\StartGame;
use App\Enums\Audience;
use App\Enums\EventStatus;
use App\Enums\QuestionStatus;
use App\Enums\ReplayKind;
use App\Models\Event;
use App\Models\Person;
use App\Models\QuestionResult;
use App\Models\User;
use App\Realtime\EventSnapshot;
use App\Realtime\StateChanged;
use App\Realtime\StatePublisher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Tests\Support\Pentahoot;
use Tests\Support\Replay;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function replayHost(Event $event): User
{
    return $event->owner()->firstOrFail();
}

describe('showing a past moment on the Public View (D-11)', function (): void {
    it('shows a revealed category, moves the version and tells every screen with what to draw', function (): void {
        $event = Pentahoot::event();
        $game = Replay::pentahoot($event);
        $question = Pentahoot::question($game);
        $version = $event->state_version;
        Events::fake([StateChanged::class]);

        actingAs(replayHost($event))->post("/host/{$event->id}/replay", ['kind' => 'result', 'game' => $game->id, 'question' => $question->id])
            ->assertRedirect();

        $event->refresh();
        expect($event->replay_kind)->toBe(ReplayKind::Result)
            ->and($event->replay_question_id)->toBe($question->id)
            ->and($event->state_version)->toBe($version + 1);
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => data_get($sent->payload, 'replay.title') === 'Most punctual?');
    });

    it('carries the replay in the snapshot of the Public View', function (): void {
        $event = Pentahoot::event();
        $game = Replay::kata($event);
        actingAs(replayHost($event))->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $game->id]);
        $event->refresh();

        $public = app(EventSnapshot::class)->for($event, Audience::Public);

        expect(data_get($public, 'replay.kind'))->toBe('final')
            ->and(data_get($public, 'replay.rows.0.name'))->toBe('Rita Wulandari');
    });

    it('shows a moment after the event has closed too', function (): void {
        $event = Pentahoot::event(attributes: ['status' => EventStatus::Finished]);
        $game = Replay::kata($event);

        actingAs(replayHost($event))->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $game->id])
            ->assertSessionHasNoErrors();

        expect($event->refresh()->replay_kind)->toBe(ReplayKind::Final);
    });

    it('refuses while a question is on screen, and a moment that was never on screen', function (): void {
        $event = Pentahoot::event();
        $played = Replay::kata($event);
        $running = Replay::pentahoot($event);
        $event->forceFill(['active_game_id' => $running->id])->save();
        $running->forceFill(['current_question_id' => Pentahoot::question($running, 2)->id])->save();
        $host = replayHost($event);

        actingAs($host)->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $played->id])
            ->assertSessionHasErrors(['action' => __('errors.QUESTION_ON_SCREEN')]);

        $running->forceFill(['current_question_id' => null])->save();
        actingAs($host)->post("/host/{$event->id}/replay", ['kind' => 'result', 'game' => $running->id, 'question' => Pentahoot::question($running, 2)->id])
            ->assertSessionHasErrors(['action' => __('errors.MOMENT_UNAVAILABLE')]);

        expect($event->refresh()->replay_kind)->toBeNull();
    });

    it('refuses a game of another event and an unknown kind', function (): void {
        $event = Pentahoot::event();
        $other = Pentahoot::event(attributes: ['slug' => 'other-party']);
        $foreign = Replay::kata($other);

        actingAs(replayHost($event))->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $foreign->id])
            ->assertSessionHasErrors('action');
        actingAs(replayHost($event))->post("/host/{$event->id}/replay", ['kind' => 'replay-everything', 'game' => $foreign->id])
            ->assertSessionHasErrors('kind');
    });

    it('lets co-hosts replay and keeps other hosts out', function (): void {
        $event = Pentahoot::event();
        $game = Replay::kata($event);
        $cohost = User::factory()->create();
        $event->cohosts()->attach($cohost);

        actingAs($cohost)->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $game->id])->assertSessionHasNoErrors();
        actingAs(User::factory()->create())->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $game->id])->assertForbidden();
        actingAs(User::factory()->create())->delete("/host/{$event->id}/replay")->assertForbidden();
    });
});

// F14, as SnapshotTest: names of 100 characters. Longer escaped text falls back to a refresh signal (F24).
it('keeps the largest replay inside the message budget (F14)', function (): void {
    $event = Pentahoot::event();
    $game = Pentahoot::game($event, [str_repeat('Which colleague ', 31).'?']);
    $question = Pentahoot::question($game);
    $question->forceFill(['status' => QuestionStatus::Revealed])->save();

    foreach (range(1, 10) as $rank) {
        QuestionResult::query()->create([
            'question_id' => $question->id,
            'attempt' => 1,
            'person_id' => Person::factory()->for($event)->create(['name' => str_pad("Name {$rank} ", 100, 'x')])->id,
            'rank' => $rank,
            'votes' => 100 - $rank,
            'frozen_at' => now(),
        ]);
    }

    actingAs(replayHost($event))->post("/host/{$event->id}/replay", ['kind' => 'result', 'game' => $game->id, 'question' => $question->id]);
    $event->refresh();
    $snapshot = app(EventSnapshot::class)->for($event, Audience::Public);

    expect($snapshot['replay']['rows'] ?? [])->toHaveCount(10)
        ->and(StatePublisher::fits(Audience::Public->channelName($event), $snapshot))->toBeTrue();
});

describe('going back to live (D-11)', function (): void {
    it('clears the replay with Back to live', function (): void {
        $event = Pentahoot::event();
        $game = Replay::kata($event);
        $host = replayHost($event);
        actingAs($host)->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $game->id]);
        $version = $event->refresh()->state_version;

        actingAs($host)->delete("/host/{$event->id}/replay")->assertRedirect();

        $event->refresh();
        expect($event->replay_kind)->toBeNull()
            ->and($event->replay_game_id)->toBeNull()
            ->and($event->state_version)->toBe($version + 1)
            ->and(app(EventSnapshot::class)->for($event, Audience::Public)['replay'])->toBeNull();
    });

    it('ends the replay when a game starts or finishes, so play always wins', function (): void {
        $event = Pentahoot::event();
        $played = Replay::kata($event);
        $next = TebakKata::game($event, title: 'Next Round');
        $host = replayHost($event);
        $event->forceFill(['names_locked_at' => now()])->save();

        actingAs($host)->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $played->id]);
        app(StartGame::class)->handle($event, $next, $host);
        expect($event->refresh()->replay_kind)->toBeNull();

        actingAs($host)->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $played->id]);
        expect($event->refresh()->replay_kind)->toBe(ReplayKind::Final);
        app(FinishGame::class)->handle($event, $next, $host);
        expect($event->refresh()->replay_kind)->toBeNull();
    });

    it('ends the replay when a question action runs', function (): void {
        $event = Pentahoot::event();
        $played = Replay::kata($event);
        $running = Pentahoot::running($event);
        $host = replayHost($event);

        actingAs($host)->post("/host/{$event->id}/replay", ['kind' => 'final', 'game' => $played->id]);
        expect($event->refresh()->replay_kind)->toBe(ReplayKind::Final);

        actingAs($host)->postJson("/host/{$event->id}/questions/".Pentahoot::question($running)->id.'/start')->assertSuccessful();

        expect($event->refresh()->replay_kind)->toBeNull()
            ->and(Pentahoot::question($running)->status)->toBe(QuestionStatus::Live);
    });
});
