<?php

declare(strict_types=1);

use App\Actions\Events\UpdateEventSettings;
use App\Actions\Games\RunQuestionAction;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Person;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Without a Reverb server the screens follow the game by polling /state (T2), so each step can
| take a few seconds to show; the plugin retries assertions until its timeout.
*/

/**
 * @param  array<string, mixed>  $input
 */
function runKata(Event $event, Question $question, string $action, array $input = []): void
{
    app(RunQuestionAction::class)->handle(
        app(GameEngines::class)->live($question->game()->firstOrFail()->type),
        $action,
        $event->refresh(),
        $question,
        $event->owner()->firstOrFail(),
        $input,
    );
}

it('shows a Tebak Kata question on the projector and mirrors it on phones only when turned on (E5, E11, E12, E14, E17)', function (): void {
    $event = Pentahoot::event(['Budi Santoso'], ['show_on_devices' => true]);
    Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);
    $game = TebakKata::running($event, [['Capital of France?', 'PARIS', [0]]]);
    $question = $game->questions()->sole();

    $screen = visit('/year-end-party/screen')->resize(1920, 1080)->assertSee('The next question is coming');
    $phone = visit('/year-end-party')->on()->iPhone15()->click('Rita Wulandari')->assertSee('Answer out loud to the host');

    runKata($event, $question, 'show');
    $screen->assertSee('Capital of France?')->assertSee('Raise your hand if you know the answer')->assertDontSee('PARIS');
    $phone->assertSee('Capital of France?');

    runKata($event, $question, 'winner', ['person' => Pentahoot::person($event, 'Budi Santoso')->id]);
    $screen->assertSee('Winner:')->assertSee('Budi Santoso');

    runKata($event, $question, 'leaderboard');
    $screen->assertSee('Leaderboard')->assertSee('Same points: whoever got there first ranks higher.');

    // E14 off: phones point to the screen instead of mirroring it.
    app(UpdateEventSettings::class)->handle($event->refresh(), $event->name, $event->slug, $event->screen_theme, false);
    $phone->assertSee('Watch the screen and answer out loud to the host.')->assertNoJavaScriptErrors();

    // E12, E17: the final podium, on the projector and (as text, whatever E14 says) on phones.
    actingAs($event->owner()->firstOrFail())->post("/host/{$event->id}/games/{$game->id}/finish");
    $screen->assertSee('Final results')->assertSee('Budi Santoso')->assertNoJavaScriptErrors();
    $phone->assertSee('Final results')->assertNoJavaScriptErrors();
});

it('runs a Tebak Kata question from Live control: show, hint, winner and leaderboard (D-5, D-6, E5, E11)', function (): void {
    $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso']);
    TebakKata::running($event, [['Capital of France?', 'PARIS', [0]], ['Largest ocean?', 'PACIFIC', [1]]]);

    actingAs($event->owner()->firstOrFail());

    $page = visit("/host/{$event->id}")
        ->assertSee('Questions (2)')
        ->click('Capital of France?')
        ->assertSee('Question 1 of 2')
        ->assertSee('Answer:')
        ->assertSee('PARIS');

    $page->click('@open-box-2')
        ->assertSeeIn('@open-box-2', 'A')
        ->type('winner-search', 'rit')
        ->click('Rita Wulandari')
        ->click('Pick winner')
        ->click('@confirm-winner')
        ->assertSee('Winner:')
        // A dialog left open would make the whole page inert.
        ->assertScript('[...document.querySelectorAll("dialog")].every((dialog) => !dialog.open)')
        ->click('@show-leaderboard')
        // The button goes once the leaderboard is on screen (E11).
        ->assertMissing('@show-leaderboard')
        ->assertNoJavaScriptErrors();
});
