<?php

declare(strict_types=1);

use App\Actions\Games\RunQuestionAction;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\Person;
use App\Models\Question;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

/*
| Without a Reverb server the screens follow the game by polling /state (T2), so each step can
| take a few seconds to show; the plugin retries assertions until its timeout.
*/

function runQuestion(Event $event, Question $question, string $action): void
{
    app(RunQuestionAction::class)->handle(
        app(GameEngines::class)->live($question->game()->firstOrFail()->type),
        $action,
        $event->refresh(),
        $question,
        $event->owner()->firstOrFail(),
    );
}

it('plays a Pentahoot question from a phone to the podium on the Public View (F3, E2, E3, E4, E4b, E17)', function (): void {
    // Rita is still free to claim on the phone; the other two joined already.
    $event = Pentahoot::event(['Budi Santoso', 'Ana Putri']);
    Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);
    $question = Pentahoot::question(Pentahoot::running($event, ['Who is the most punctual?']));

    $screen = visit('/year-end-party/screen')->resize(1920, 1080)->assertSee('The next question is coming');
    $phone = visit('/year-end-party')->on()->iPhone15()->click('Rita Wulandari')->assertSee('Get ready for the next question');

    runQuestion($event, $question, 'start');

    $phone->assertSee('Who is the most punctual?')
        ->type('vote-search', 'bud')
        ->click('Budi Santoso')
        ->press('Send my answer')
        ->assertSee('Answer sent')
        ->assertSee('Budi Santoso');
    $screen->assertSee('Who is the most punctual?')->assertSee('1 / 3 answered');

    runQuestion($event, $question, 'stop');
    travelTo(now()->addSeconds(2));
    runQuestion($event, $question, 'reveal');

    $screen->assertSee('1 vote')->assertSee('Budi Santoso')->assertNoJavaScriptErrors();
    $phone->assertSee('Results of question 1')->assertSee('Your pick: Budi Santoso')->assertNoJavaScriptErrors();
});

it('runs a question from Live control: open, stop, reveal and move on (D-5, F2)', function (): void {
    $event = Pentahoot::event();
    Pentahoot::running($event, ['Most punctual?', 'Best presenter?']);

    actingAs($event->owner()->firstOrFail());

    $page = visit("/host/{$event->id}")
        ->assertSee('Questions (2)')
        ->click('Best presenter?')
        ->assertSee('Question 2 of 2 · Live')
        ->assertSee('0 / 3 answered');

    // "Time up" only shows once the server applied Stop (the new ends_at comes from the server).
    // Move the clock after that: travelTo freezes the server clock, and a Stop that reached the
    // server after the jump would end at the frozen time and never close (E3).
    $page->click('Stop')->assertSee('Question 2 of 2 · Time up');
    travelTo(now()->addSeconds(2));

    $page->click('Reveal')
        ->assertSee('No votes')
        ->click('Next question')
        ->assertSee('Pick a question to open.')
        ->assertNoJavaScriptErrors();
});
