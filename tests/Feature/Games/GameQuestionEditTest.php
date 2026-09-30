<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

uses(RefreshDatabase::class);

function copyUrl(Event $event, Game $game, Question $question): string
{
    return "/host/{$event->id}/games/{$game->id}/questions/{$question->id}";
}

describe('editing copied questions (D-2)', function (): void {
    it('lists a game\'s questions and edits a Pentahoot copy without touching its pack', function (): void {
        $event = Pentahoot::event();
        $pack = Pentahoot::pack($event->owner()->firstOrFail(), ['Pack prompt?']);
        actingAs($event->owner()->firstOrFail())->post("/host/{$event->id}/games", ['pack_id' => $pack->id]);
        $game = Game::query()->sole();
        $copy = $game->questions()->sole();

        get("/host/{$event->id}/games/{$game->id}/questions")->assertOk()->assertSee('Pack prompt?')->assertSee(copyUrl($event, $game, $copy).'/edit');
        get(copyUrl($event, $game, $copy).'/edit')->assertOk()->assertSee('Pack prompt?');

        put(copyUrl($event, $game, $copy), ['prompt' => 'Copy prompt?', 'duration_seconds' => 45])
            ->assertRedirect("/host/{$event->id}/games/{$game->id}/questions")
            ->assertSessionHas('status', 'Question saved. The pack is not changed.');

        expect($copy->pentahoot()->firstOrFail()->only(['prompt', 'duration_seconds']))->toBe(['prompt' => 'Copy prompt?', 'duration_seconds' => 45])
            ->and($pack->questions()->firstOrFail()->pentahoot()->firstOrFail()->prompt)->toBe('Pack prompt?');
    });

    it('edits a Tebak Kata copy and resets its open boxes to the new start', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::game($event, [['Capital?', 'PARIS', [0]]]);
        $copy = $game->questions()->sole();

        actingAs($event->owner()->firstOrFail())->put(copyUrl($event, $game, $copy), [
            'prompt' => 'Capital of Italy?',
            'answer_text' => 'ROME',
            'initial_open_indexes' => [1],
            'points' => 2,
        ])->assertRedirect();

        $detail = $copy->kata()->firstOrFail();
        expect($detail->answer_text)->toBe('ROME')
            ->and($detail->initial_open_indexes)->toBe([1])
            ->and($detail->opened_indexes)->toBe([1])
            ->and($copy->refresh()->points)->toBe(2);
    });

    it('validates with the rules of the game type', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);
        $copy = Pentahoot::question($game);

        actingAs($event->owner()->firstOrFail())->put(copyUrl($event, $game, $copy), ['prompt' => 'Q?', 'duration_seconds' => 400])
            ->assertSessionHasErrors('duration_seconds');
    });

    it('locks questions that have been on screen, and every question of a finished game', function (): void {
        $event = Pentahoot::event();
        $game = TebakKata::game($event, [['A?', 'AB'], ['B?', 'CD']]);
        $shown = $game->questions()->where('position', 1)->firstOrFail();
        $shown->forceFill(['status' => QuestionStatus::Queued, 'skip_used' => true])->save();
        $fresh = $game->questions()->where('position', 2)->firstOrFail();
        $body = ['prompt' => 'X?', 'answer_text' => 'XY', 'points' => 1];

        actingAs($event->owner()->firstOrFail());
        get("/host/{$event->id}/games/{$game->id}/questions")->assertDontSee(copyUrl($event, $game, $shown).'/edit');
        from("/host/{$event->id}/games/{$game->id}/questions")->put(copyUrl($event, $game, $shown), $body)
            ->assertSessionHasErrors(['action' => 'This question has been on screen, so it can no longer be edited.']);

        $game->forceFill(['status' => GameStatus::Finished])->save();
        put(copyUrl($event, $game, $fresh), $body)->assertSessionHasErrors('action');
    });

    it('keeps other hosts out and questions inside their own game', function (): void {
        $event = Pentahoot::event();
        $game = Pentahoot::game($event);
        $other = Pentahoot::game($event, ['Other?'], 'Other game');

        actingAs(User::factory()->create())->get(copyUrl($event, $game, Pentahoot::question($game)).'/edit')->assertForbidden();
        actingAs($event->owner()->firstOrFail())->get(copyUrl($event, $game, Pentahoot::question($other)).'/edit')->assertNotFound();
    });
});
