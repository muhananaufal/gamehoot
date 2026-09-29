<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\Event;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Pest\Browser\Enums\BrowserType;
use Pest\Browser\Playwright\Playwright;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('signs a host in through the form (C-5)', function (): void {
    User::factory()->create(['email' => 'arif@company.test', 'password' => 'browser-test-42']);

    visit('/login')
        ->type('email', 'arif@company.test')
        ->type('password', 'browser-test-42')
        ->press('Sign in')
        ->assertSee('No events yet.')
        ->assertNoJavaScriptErrors();
})->skip(
    fn (): bool => Playwright::defaultBrowserType() === BrowserType::SAFARI,
    'WebKit: pest-plugin-browser 5.0.1 cannot wait for the navigation after a successful sign in (pestphp/pest#1511).',
);

it('lets a host create an event and add names (B-4, F10)', function (): void {
    $host = User::factory()->create();
    Event::factory()->create(['slug' => 'gathering-2026']);

    actingAs($host);

    visit('/host')
        ->assertSee('No events yet.')
        ->click('New event')
        ->type('name', 'Gathering 2026')
        ->press('Create event')
        ->assertSee('Event created. Next, add the names of the people who can play.')
        ->assertPathEndsWith('/people')
        ->type('add-person-name', 'Budi Santoso')
        ->press('Add name')
        ->assertSee('Name added.')
        ->type('add-person-name', 'budi  SANTOSO')
        ->press('Add name')
        ->assertSee('Same as “Budi Santoso” already in this event')
        // The CSV upload itself is covered by feature tests: the browser plugin's test server
        // does not forward multipart bodies (W11).
        ->click('Import CSV')
        ->assertSee('CSV file')
        ->assertNoJavaScriptErrors();

    expect(Event::query()->where('owner_id', $host->id)->value('slug'))->toBe('gathering-2026-2');
});

it('previews Tebak Kata boxes and saves the ones opened from the start (E5)', function (): void {
    $host = User::factory()->create();
    $pack = new QuestionPack(['title' => 'Celebrities', 'game_type' => GameType::TebakKata]);
    $pack->owner()->associate($host)->save();

    actingAs($host);

    visit("/host/packs/{$pack->id}/questions/create")
        ->type('prompt', 'Actress and judge on a modeling show')
        ->type('answer_text', 'Luna Maya')
        ->assertSee('0 of 8 open')
        ->click('[aria-label="Box 1, letter L"]')
        ->assertSee('1 of 8 open')
        ->press('Save question')
        ->assertSee('Question added.')
        ->assertSee('LUNA MAYA')
        ->assertNoJavaScriptErrors();

    expect($pack->questions()->with('kata')->firstOrFail()->kata?->initial_open_indexes)->toBe([0]);
});

it('opens the host pages without JavaScript errors', function (): void {
    $host = User::factory()->superAdmin()->create();
    $event = Event::factory()->for($host, 'owner')->create();

    actingAs($host);

    visit([
        '/host',
        '/host/events/create',
        "/host/{$event->id}/settings",
        "/host/{$event->id}/people",
        '/host/packs',
        '/host/trash',
        '/admin/users',
        '/admin/trash',
    ])->assertNoJavaScriptErrors();
});
