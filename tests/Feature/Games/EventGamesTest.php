<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Game;
use App\Models\QuestionPack;
use App\Models\User;
use App\Realtime\StateChanged;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as Events;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

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
            ->assertDontSee('Celebrities');
    });

    it('refuses packs of another host and game types that cannot be played yet', function (): void {
        $event = Pentahoot::event();
        $foreign = Pentahoot::pack(User::factory()->create());
        $kata = new QuestionPack(['title' => 'Celebrities', 'game_type' => GameType::TebakKata]);
        $kata->owner()->associate(gamesOwner($event))->save();

        actingAs(gamesOwner($event));
        post("/host/{$event->id}/games", ['pack_id' => $foreign->id])->assertSessionHasErrors('pack_id');
        post("/host/{$event->id}/games", ['pack_id' => $kata->id])->assertSessionHasErrors('pack_id');
        expect(Game::query()->count())->toBe(0);
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

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/finish")
            ->assertRedirect("/host/{$event->id}");

        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and($event->refresh()->active_game_id)->toBeNull()
            ->and(ActionLog::query()->where('action', LoggedAction::GameFinishedEarly)->count())->toBe(1);
    });

    it('finishes a completed game without an early-finish log entry', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event);
        $game->questions()->update(['status' => QuestionStatus::Done]);

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/finish");

        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            ->and(ActionLog::query()->count())->toBe(0);
    });

    it('cannot start a finished game again (D-4)', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);
        $game->forceFill(['status' => GameStatus::Finished])->save();

        actingAs(gamesOwner($event))->post("/host/{$event->id}/games/{$game->id}/start")
            ->assertSessionHasErrors(['action' => 'This game has already been played.']);
    });
});
