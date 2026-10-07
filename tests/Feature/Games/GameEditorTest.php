<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Game;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

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
