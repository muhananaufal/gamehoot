<?php

declare(strict_types=1);

use App\Actions\Games\StartGame;
use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\PackQuestion;
use App\Models\QuestionPack;
use App\Models\User;
use App\Realtime\StateChanged;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

function gamesOwner(Event $event): User
{
    return $event->owner()->firstOrFail();
}

describe('games in an event (D-9)', function (): void {
    it('copies a Pentahoot pack into a new game at the end of the list', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event, ['Old question?'], 'Warm-up');
        $pack = Pentahoot::pack(gamesOwner($event), ['Most punctual?', 'Best presenter?'], 'Office Awards');

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games", ['pack_id' => $pack->id])
            ->assertRedirect("/host/{$event->id}/games")
            ->assertSessionHas('status', 'Game added. Its questions are copies of the pack.');

        $game = Game::query()->where('title', 'Office Awards')->firstOrFail();
        expect($game->position)->toBe(2)
            ->and($game->source_pack_id)->toBe($pack->id)
            ->and($game->questions()->with('pentahoot')->get()->map(fn ($q) => $q->pentahoot?->prompt)->all())
            ->toBe(['Most punctual?', 'Best presenter?']);
    });

    it('lists the games and the packs that can be played', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event, ['Old question?'], 'Warm-up');
        Pentahoot::pack(gamesOwner($event), ['Q?'], 'Office Awards');
        $kata = new QuestionPack(['title' => 'Celebrities', 'game_type' => GameType::TebakKata]);
        $kata->owner()->associate(gamesOwner($event))->save();

        actingAs(gamesOwner($event))->get("/host/{$event->id}/games")
            ->assertOk()
            ->assertSee('Warm-up')
            ->assertSee('Office Awards')
            // Tebak Kata is playable from stage 4.
            ->assertSee('Celebrities')
            ->assertDontSee('arrive in later releases');
    });

    it('offers the pack list of Question packs in Add games, several packs at once (D-9)', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(gamesOwner($event), ['Q?'], 'Office Awards');
        $kata = new QuestionPack(['title' => 'Celebrities', 'game_type' => GameType::TebakKata]);
        $kata->owner()->associate(gamesOwner($event))->save();

        $html = (string) actingAs(gamesOwner($event))->get("/host/{$event->id}/games")->assertOk()->getContent();

        expect($html)->toContain('<nav id="pack-list" x-data="packList"')
            ->and($html)->toContain('data-group="pentahoot"')
            ->and($html)->toContain('data-group="tebak_kata"')
            ->and($html)->toMatch('#<button type="button" data-pack data-type="pentahoot"[^>]*data-title="Office Awards"#')
            ->and($html)->toContain('action="'.route('host.events.games.store', $event).'"')
            ->and($html)->toContain('name="pack_ids[]"');
    });

    it('tells a host without packs where to write one, whatever the game type', function (): void {
        $event = Pentahoot::event();

        actingAs(gamesOwner($event))->get("/host/{$event->id}/games")
            ->assertOk()
            ->assertSee('You have no question packs yet. Write one under Question packs first.');
    });

    it('refuses packs of another host', function (): void {
        $event = Pentahoot::event();
        $foreign = Pentahoot::pack(User::factory()->create());

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games", ['pack_id' => $foreign->id])->assertSessionHasErrors('pack_ids.0');
        expect(Game::query()->count())->toBe(0);
    });

    it('adds a Tebak Kata game from a pack, copying the answers and open boxes (D-9, stage 4)', function (): void {
        $event = Pentahoot::event();
        $kata = new QuestionPack(['title' => 'Celebrities', 'game_type' => GameType::TebakKata]);
        $kata->owner()->associate(gamesOwner($event))->save();
        $source = new PackQuestion(['position' => 1, 'points' => 2]);
        $source->pack()->associate($kata)->save();
        $source->kata()->forceCreate(['prompt' => 'Capital of France?', 'answer_text' => 'PARIS', 'initial_open_indexes' => [0]]);

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games", ['pack_id' => $kata->id])->assertSessionHasNoErrors();

        $copy = Game::query()->sole()->questions()->sole();
        expect($copy->status)->toBe(QuestionStatus::Queued)
            ->and($copy->points)->toBe(2)
            ->and($copy->kata?->opened_indexes)->toBe([0]);
    });

    it('reloads an unstarted game from the latest pack', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(gamesOwner($event), ['First version?']);
        actingAs(gamesOwner($event))->post("/host/{$event->id}/games", ['pack_id' => $pack->id]);
        $game = Game::query()->firstOrFail();
        $pack->questions()->firstOrFail()->pentahoot()->update(['prompt' => 'Second version?']);

        post("/host/{$event->id}/games/{$game->id}/reload")->assertRedirect("/host/{$event->id}/games");

        expect($game->questions()->firstOrFail()->pentahoot()->firstOrFail()->prompt)->toBe('Second version?');
    });

    it('deletes an unplayed game but keeps a played one (T8)', function (): void {
        $event = Pentahoot::event();
        $unplayed = Pentahoot::game($event);
        $played = Pentahoot::game($event);
        Pentahoot::question($played)->forceFill(['status' => QuestionStatus::Done])->save();

        actingAs(gamesOwner($event));
        delete("/host/{$event->id}/games/{$unplayed->id}")->assertRedirect("/host/{$event->id}/games");
        from("/host/{$event->id}/games")->delete("/host/{$event->id}/games/{$played->id}")
            ->assertSessionHasErrors(['action' => 'This game has been played, so it is kept for the results.']);

        expect(Game::query()->pluck('id')->all())->toBe([$played->id]);
    });

    it('keeps other hosts out', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);

        actingAs(User::factory()->create());
        get("/host/{$event->id}/games")->assertForbidden();
        post("/host/{$event->id}/games/{$game->id}/start")->assertForbidden();
    });
});

describe('starting and finishing a game (D-1, D-8, B-3)', function (): void {
    it('asks before the first game locks the name list, and not after (B-3)', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event);
        $warning = 'Starting the first game locks the name list';

        // D-10: games start from Live control.
        actingAs(gamesOwner($event))->get("/host/{$event->id}")->assertSee($warning);

        $event->forceFill(['names_locked_at' => now()])->save();
        actingAs(gamesOwner($event))->get("/host/{$event->id}")->assertDontSee($warning);
    });

    it('starts a game, locks the name list and tells every screen', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);
        Events::fake([StateChanged::class]);

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/start")
            ->assertRedirect("/host/{$event->id}");

        $event->refresh();
        expect($event->active_game_id)->toBe($game->id)
            ->and($event->names_locked_at)->not->toBeNull();
        Events::assertDispatched(StateChanged::class, fn (StateChanged $sent): bool => data_get($sent->payload, 'game.id') === $game->id);
    });

    it('refuses to start a second game, a game without questions, or while the event is not open', function (): void {
        $event = Pentahoot::event();
        $running = Pentahoot::running($event);
        $other = Pentahoot::game($event);
        $empty = Game::factory()->for($event)->create(['type' => GameType::Pentahoot, 'position' => 9]);
        $draft = Pentahoot::event([], ['slug' => 'draft-event', 'status' => EventStatus::Draft]);
        $draftGame = Pentahoot::game($draft);

        actingAs(gamesOwner($event));
        from("/host/{$event->id}")->post("/host/{$event->id}/games/{$other->id}/start")
            ->assertSessionHasErrors(['action' => 'Another game is running. Finish it first.']);

        $event->forceFill(['active_game_id' => null])->save();
        post("/host/{$event->id}/games/{$empty->id}/start")
            ->assertSessionHasErrors(['action' => 'This game has no questions yet.']);

        actingAs(gamesOwner($draft))->post("/host/{$draft->id}/games/{$draftGame->id}/start")
            ->assertSessionHasErrors(['action' => 'Open the event before starting a game.']);

        expect($running->refresh()->status)->toBe(GameStatus::Draft);
    });

    it('finishes a game early and logs it, leaving unfinished questions out (D-8, E13)', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        Pentahoot::question($game, 1)->forceFill(['status' => QuestionStatus::Done])->save();

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $event->fresh()?->state_version])
            ->assertRedirect("/host/{$event->id}");

        // G7, E12: the finished game stays on screen with its results until the next game starts.
        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and($event->refresh()->active_game_id)->toBe($game->id)
            ->and(ActionLog::query()->where('action', LoggedAction::GameFinishedEarly)->count())->toBe(1);
    });

    it('starts the next game in place of a finished one on screen (G7, E12)', function (): void {
        $event = Pentahoot::event();
        $first = Pentahoot::running($event);
        $first->forceFill(['status' => GameStatus::Finished])->save();
        $next = Pentahoot::game($event, ['Next?'], 'Second round');

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$next->id}/start")
            ->assertRedirect("/host/{$event->id}");

        expect($event->refresh()->active_game_id)->toBe($next->id);
    });

    it('does not finish a game twice', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $game->forceFill(['status' => GameStatus::Finished])->save();

        actingAs(gamesOwner($event))->from("/host/{$event->id}")->post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $event->fresh()?->state_version])
            ->assertSessionHasErrors(['action' => 'The screen was out of date. It has been refreshed, try again.']);
    });

    it('finishes a completed game, logged as finished rather than finished early', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $game->questions()->update(['status' => QuestionStatus::Done]);

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $event->fresh()?->state_version]);

        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and(ActionLog::query()->pluck('action')->all())->toBe([LoggedAction::GameFinished]);
    });

    it('cannot start a finished game again (D-4)', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);
        $game->forceFill(['status' => GameStatus::Finished])->save();

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/start")
            ->assertSessionHasErrors(['action' => 'This game has already been played.']);
    });
});

it('says when each started game started, from the activity log (U6)', function (): void {
    $event = Pentahoot::event();
    $owner = $event->owner()->firstOrFail();
    $game = Pentahoot::game($event, title: 'Office Awards');
    $second = Pentahoot::game($event, title: 'Second Round');

    travelTo(CarbonImmutable::parse('2026-10-02 04:30:00'));
    app(StartGame::class)->handle($event, $game, $owner);

    actingAs($owner)->get("/host/{$event->id}/games/{$game->id}")->assertSee('Started 2 Oct, 11:30');
    actingAs($owner)->get("/host/{$event->id}/games/{$second->id}")->assertSee('Added', false);
});

describe('finishing after the last question (D-8, D-10)', function (): void {
    it('closes a revealed last question as part of Finish game, without calling it early', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event, ['Only?']);
        $only = $game->questions()->sole();
        $owner = gamesOwner($event);
        actingAs($owner);
        foreach (['start', 'stop'] as $action) {
            post("/host/{$event->id}/questions/{$only->id}/{$action}");
        }
        travelTo(now()->addSeconds(2));
        post("/host/{$event->id}/questions/{$only->id}/reveal");

        post("/host/{$event->id}/games/{$game->id}/finish", ['version' => $event->fresh()?->state_version])->assertSessionHasNoErrors();

        expect($only->refresh()->status)->toBe(QuestionStatus::Done)
            ->and($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and(ActionLog::query()->where('action', LoggedAction::GameFinishedEarly)->exists())->toBeFalse();
    });
});

describe('the order of the games still to play (D-9)', function (): void {
    it('reorders the games not played yet into the places they hold, leaving played ones where they are', function (): void {
        $event = Pentahoot::event();
        $played = Pentahoot::game($event, title: 'Warm-up');
        $played->forceFill(['status' => GameStatus::Finished])->save();
        $event->forceFill(['active_game_id' => $played->id])->save();
        $second = Pentahoot::game($event, title: 'Second');
        $third = Pentahoot::game($event, title: 'Third');
        $fourth = Pentahoot::game($event, title: 'Fourth');

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/reorder", ['order' => [$fourth->id, $second->id, $third->id]])
            ->assertSessionHasNoErrors();

        expect($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Warm-up', 'Fourth', 'Second', 'Third'])
            ->and($played->refresh()->position)->toBe(1);
    });

    it('refuses an order that leaves out a game or moves a played one', function (): void {
        $event = Pentahoot::event();
        $played = Pentahoot::game($event, title: 'Warm-up');
        $played->forceFill(['status' => GameStatus::Finished])->save();
        $waiting = Pentahoot::game($event, title: 'Second');
        $other = Pentahoot::game($event, title: 'Third');
        actingAs(gamesOwner($event));

        post("/host/{$event->id}/games/reorder", ['order' => [$other->id]])->assertSessionHasErrors('order');
        post("/host/{$event->id}/games/reorder", ['order' => [$other->id, $played->id, $waiting->id]])->assertSessionHasErrors('order');

        expect($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Warm-up', 'Second', 'Third']);
    });
});

describe('the Games page (D-9)', function (): void {
    it('lists the games by status, the ones still to play first and in a draggable order (D-9)', function (): void {
        $event = Pentahoot::event();
        $played = Pentahoot::game($event, title: 'Warm-up');
        $played->forceFill(['status' => GameStatus::Finished])->save();
        $event->forceFill(['active_game_id' => $played->id])->save();
        $next = Pentahoot::game($event, title: 'Team Trivia');

        $html = (string) actingAs(gamesOwner($event))->get("/host/{$event->id}/games")->getContent();

        expect($html)->toMatch('#Up next · 1.*Team Trivia.*Played · 1.*Warm-up#s')
            ->and($html)->toContain('action="'.route('host.events.games.reorder', $event).'"')
            ->and($html)->toContain('data-id="'.$next->id.'" draggable="true"')
            ->and($html)->not->toContain('data-id="'.$played->id.'" draggable');
    });

    it('filters the games by type, the one filter of the list (D-9)', function (): void {
        $event = Pentahoot::event();
        $trivia = Pentahoot::game($event, title: 'Team Trivia');
        $words = TebakKata::game($event, title: 'Word Round');

        $html = (string) actingAs(gamesOwner($event))->get("/host/{$event->id}/games")->getContent();

        expect($html)->toContain('aria-label="Game type"')
            ->and($html)->toContain('All game types')
            ->and($html)->toContain('data-type="pentahoot" data-game="'.$trivia->id.'"')
            ->and($html)->toContain('data-type="tebak_kata" data-game="'.$words->id.'"');
    });

    it('marks edited games and deleted packs, and offers Reload only while the pack is there (D-9)', function (): void {
        $event = Pentahoot::event();
        $owner = gamesOwner($event);
        $pack = Pentahoot::pack($owner, ['First?'], 'Office Awards');
        actingAs($owner)->post("/host/{$event->id}/games", ['pack_ids' => [$pack->id]]);
        $game = Game::query()->sole();
        $game->forceFill(['edited_at' => now()])->save();

        $html = (string) get("/host/{$event->id}/games/{$game->id}")->getContent();
        expect($html)->toContain('Edited in this event')
            ->and($html)->toContain('data-reload="1"');

        $pack->delete();
        $after = (string) get("/host/{$event->id}/games/{$game->id}")->getContent();
        expect($after)->toContain('Pack deleted')
            ->and($after)->not->toContain('data-reload="1"');
    });

    it('warns about lost edits only when the game was edited', function (): void {
        $event = Pentahoot::event();
        $owner = gamesOwner($event);
        $pack = Pentahoot::pack($owner, ['First?'], 'Office Awards');
        actingAs($owner)->post("/host/{$event->id}/games", ['pack_ids' => [$pack->id]]);
        $game = Game::query()->sole();
        post("/host/packs/{$pack->id}/questions", ['prompt' => 'New one?', 'duration_seconds' => 20]);

        expect((string) get("/host/{$event->id}/games/{$game->id}")->getContent())->not->toContain('Edits made to this game’s questions are lost.');

        $game->forceFill(['edited_at' => now()])->save();
        expect((string) get("/host/{$event->id}/games/{$game->id}")->getContent())->toContain('Edits made to this game’s questions are lost.');
    });
});

describe('finished is yellow (D-10)', function (): void {
    it('shows a finished game read-only, in yellow, with its results (D-2, D-10)', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event, ['Only?']);
        $game->forceFill(['status' => GameStatus::Finished])->save();

        $html = (string) actingAs(gamesOwner($event))->get("/host/{$event->id}/games/{$game->id}")->assertOk()->getContent();

        expect($html)->toMatch('/bg-warning[^"]*"[^>]*>\s*Finished/')
            ->and($html)->toContain('This game has started, so it can no longer be changed.')
            ->and($html)->toContain('href="'.route('host.events.results', $event).'"')
            ->and($html)->not->toContain('data-menu');
    });
});

describe('the pack change marker (D-9)', function (): void {
    function versionOf(QuestionPack $pack): int
    {
        return QuestionPack::query()->findOrFail($pack->id)->content_version;
    }

    it('moves the pack version on every change that reload would copy, and only then', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(gamesOwner($event), ['First?', 'Second?']);
        [$first, $second] = $pack->questions()->get()->all();
        actingAs(gamesOwner($event));
        $base = "/host/packs/{$pack->id}";
        $question = ['prompt' => 'Third?', 'duration_seconds' => 20];

        get($base)->assertOk();
        expect(versionOf($pack))->toBe(0);

        post("{$base}/questions", $question);
        expect(versionOf($pack))->toBe(1);
        Pest\Laravel\put("{$base}/questions/{$first->id}", ['prompt' => 'First, edited?', 'duration_seconds' => 20]);
        expect(versionOf($pack))->toBe(2);
        post("{$base}/questions/reorder", ['order' => [$second->id, $first->id, ...$pack->questions()->whereKeyNot([$first->id, $second->id])->pluck('id')->all()]]);
        expect(versionOf($pack))->toBe(3);
        delete("{$base}/questions/{$second->id}");
        expect(versionOf($pack))->toBe(4);
        Pest\Laravel\put($base, ['title' => 'Renamed pack']);
        expect(versionOf($pack))->toBe(5);
    });

    it('records the copied version, marks a changed pack, and offers reload only then', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(gamesOwner($event), ['First?'], 'Office Awards');
        actingAs(gamesOwner($event))->post("/host/{$event->id}/games", ['pack_ids' => [$pack->id]]);
        $game = Game::query()->sole();
        $page = "/host/{$event->id}/games/{$game->id}";

        expect($game->pack_version)->toBe(0);
        get($page)->assertDontSee('Pack changed since added')->assertDontSee('data-reload="1"', false);

        post("/host/packs/{$pack->id}/questions", ['prompt' => 'New one?', 'duration_seconds' => 20]);
        // D-10: yellow means finished only, so the marker is purple.
        expect((string) get($page)->getContent())->toMatch('/bg-accent[^"]*"[^>]*>\s*Pack changed since added/');
        get($page)->assertSee('data-reload="1"', false);

        post("/host/{$event->id}/games/{$game->id}/reload");
        expect($game->refresh()->pack_version)->toBe(1);
        get($page)->assertDontSee('Pack changed since added');
    });

    it('treats a game copied before the marker existed as version 0', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(gamesOwner($event), ['First?']);
        actingAs(gamesOwner($event))->post("/host/{$event->id}/games", ['pack_id' => $pack->id]);
        Game::query()->update(['pack_version' => null]);

        get("/host/{$event->id}/games")->assertDontSee('Pack changed since added');
    });
});
