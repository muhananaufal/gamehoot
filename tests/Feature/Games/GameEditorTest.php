<?php

declare(strict_types=1);

use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\from;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

function editorOwner(Event $event): User
{
    return $event->owner()->firstOrFail();
}

/**
 * @return list<string>
 */
function promptsOf(Game $game): array
{
    return array_values($game->questions()->orderBy('position')->with('pentahoot')->get()->map(fn ($question): string => (string) $question->pentahoot?->prompt)->all());
}

describe('editing a game before it starts (D-2, D-9)', function (): void {
    it('reorders the copied questions of a game that has not started', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event, ['First?', 'Second?', 'Third?']);
        $ids = $game->questions()->orderBy('position')->pluck('id')->all();

        actingAs(editorOwner($event))->post("/host/{$event->id}/games/{$game->id}/questions/reorder", ['order' => [$ids[2], $ids[0], $ids[1]]])
            ->assertSessionHasNoErrors();

        expect(promptsOf($game))->toBe(['Third?', 'First?', 'Second?'])
            ->and($game->refresh()->edited_at)->not->toBeNull();
    });

    it('deletes a copied question, and leaves the pack alone', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(editorOwner($event), ['First?', 'Second?']);
        actingAs(editorOwner($event))->post("/host/{$event->id}/games", ['pack_ids' => [$pack->id]]);
        $game = Game::query()->sole();
        $first = $game->questions()->orderBy('position')->firstOrFail();

        delete("/host/{$event->id}/games/{$game->id}/questions/{$first->id}")->assertSessionHasNoErrors();

        expect(promptsOf($game))->toBe(['Second?'])
            ->and($pack->questions()->count())->toBe(2)
            ->and($game->refresh()->edited_at)->not->toBeNull();
    });

    it('renames a game; Reload brings the pack title back', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(editorOwner($event), ['First?'], 'Office Awards');
        actingAs(editorOwner($event))->post("/host/{$event->id}/games", ['pack_ids' => [$pack->id]]);
        $game = Game::query()->sole();

        put("/host/{$event->id}/games/{$game->id}", ['title' => 'Office Awards, night two'])->assertSessionHasNoErrors();
        expect($game->refresh()->title)->toBe('Office Awards, night two');

        post("/host/{$event->id}/games/{$game->id}/reload");
        expect($game->refresh()->title)->toBe('Office Awards');
    });

    it('refuses every change once the game has started', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event, ['First?', 'Second?']);
        $second = Pentahoot::question($game, 2);
        actingAs(editorOwner($event));
        $back = "/host/{$event->id}/games/{$game->id}";

        from($back)->put("/host/{$event->id}/games/{$game->id}/questions/{$second->id}", ['prompt' => 'Changed?', 'duration_seconds' => 20])->assertSessionHasErrors('action');
        from($back)->delete("/host/{$event->id}/games/{$game->id}/questions/{$second->id}")->assertSessionHasErrors('action');
        from($back)->post("/host/{$event->id}/games/{$game->id}/questions/reorder", ['order' => $game->questions()->orderByDesc('position')->pluck('id')->all()])->assertSessionHasErrors('action');
        from($back)->put("/host/{$event->id}/games/{$game->id}", ['title' => 'Renamed'])->assertSessionHasErrors('action');

        expect(promptsOf($game))->toBe(['First?', 'Second?'])
            ->and($game->refresh()->title)->toBe('Office Awards');
    });
});

describe('adding several games at once (D-9)', function (): void {
    it('adds the picked packs as games at the end, in the order they were picked', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event, title: 'Warm-up');
        $first = Pentahoot::pack(editorOwner($event), ['Q?'], 'Team Trivia');
        $second = Pentahoot::pack(editorOwner($event), ['Q?'], 'Office Awards');

        actingAs(editorOwner($event))->post("/host/{$event->id}/games", ['pack_ids' => [$second->id, $first->id]])
            ->assertSessionHasNoErrors();

        expect($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Warm-up', 'Office Awards', 'Team Trivia']);
    });

    it('refuses a pick without packs or with a pack twice', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(editorOwner($event), ['Q?']);
        actingAs(editorOwner($event));

        post("/host/{$event->id}/games", ['pack_ids' => []])->assertSessionHasErrors('pack_ids');
        post("/host/{$event->id}/games", ['pack_ids' => [$pack->id, $pack->id]])->assertSessionHasErrors();

        expect($event->games()->count())->toBe(0);
    });
});

describe('questions for this event only (D-2)', function (): void {
    it('adds a question at the end of a game that has not started, and leaves the pack alone', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack(editorOwner($event), ['First?']);
        actingAs(editorOwner($event))->post("/host/{$event->id}/games", ['pack_ids' => [$pack->id]]);
        $game = Game::query()->sole();

        Pest\Laravel\get("/host/{$event->id}/games/{$game->id}/questions/create")->assertOk()
            ->assertSee('action="'.route('host.events.games.questions.store', [$event, $game]).'"', false);
        post("/host/{$event->id}/games/{$game->id}/questions", ['prompt' => 'Event only?', 'duration_seconds' => 30])
            ->assertRedirect("/host/{$event->id}/games/{$game->id}");

        expect(promptsOf($game))->toBe(['First?', 'Event only?'])
            ->and($game->questions()->where('position', 2)->firstOrFail()->status)->toBe(QuestionStatus::Ready)
            ->and($pack->questions()->count())->toBe(1)
            ->and($game->refresh()->edited_at)->not->toBeNull();
    });

    it('refuses a new question once the game has started', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::running($event, ['First?']);

        actingAs(editorOwner($event))->from("/host/{$event->id}/games/{$game->id}")
            ->post("/host/{$event->id}/games/{$game->id}/questions", ['prompt' => 'Late?', 'duration_seconds' => 30])
            ->assertSessionHasErrors('action');

        expect(promptsOf($game))->toBe(['First?']);
    });

    it('adds a Tebak Kata question for this event only, with its opened letters', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::game($event, [['A?', 'AB']]);

        actingAs(editorOwner($event))->post("/host/{$event->id}/games/{$game->id}/questions", ['prompt' => 'Capital?', 'answer_text' => 'JAKARTA', 'initial_open_indexes' => [0], 'points' => 2])
            ->assertSessionHasNoErrors();

        $added = $game->questions()->where('position', 2)->with('kata')->firstOrFail();
        expect($added->kata?->answer_text)->toBe('JAKARTA')
            ->and($added->kata?->opened_indexes)->toBe([0])
            ->and($added->points)->toBe(2);
    });
});
