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

    $page = visit('/login')
        ->type('login', 'arif@company.test')
        ->type('password', 'browser-test-42');
    // The submit navigates; a retried press() would look for the button on the next page
    // (docs/debugging/2026-10-02-browser-click-timeout-after-navigation.md).
    $page->script('document.querySelector("form button[type=submit]").click()');

    $page->assertSee('No events yet.')
        ->assertNoJavaScriptErrors();
})->skip(
    fn (): bool => Playwright::defaultBrowserType() === BrowserType::SAFARI,
    'WebKit: pest-plugin-browser 5.0.1 cannot wait for the navigation after a successful sign in (pestphp/pest#1511).',
);

it('lets a host create an event and add names (B-4, F10)', function (): void {
    $host = User::factory()->create();
    Event::factory()->create(['slug' => 'gathering-2026']);

    actingAs($host);

    // U13: New event opens a dialog already in the page; once created, the main page shows the
    // new event's Names page. Clicks that open or send a dialog go through a script, so the browser
    // plugin cannot retry them (docs/debugging/2026-10-02-browser-click-timeout-after-navigation.md).
    $page = visit('/host')->assertSee('No events yet.');
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "New event").click()');
    $page->assertScript('!! document.querySelector("dialog[open] form")')
        ->assertPathEndsWith('/host')
        ->type('name', 'Gathering 2026');
    $page->script('[...document.querySelectorAll("dialog[open] button[type=submit]")].pop().click()');

    $page->assertSee('Event created. Next, add the names of the people who can play.')
        ->assertPathEndsWith('/people')
        ->assertScript('! document.querySelector("dialog[open]")')
        // U2: Add name opens a dialog in the content header; Enter sends it instead of cancelling.
        ->click('Add name')
        ->type('add-person-name', 'Budi Santoso')
        ->keys('add-person-name', 'Enter')
        ->assertSee('Name added.')
        ->click('Add name')
        ->type('add-person-name', 'budi  SANTOSO')
        ->keys('add-person-name', 'Enter')
        ->assertSee('Same as “Budi Santoso” already in this event')
        // The dialog stays open with the error; Escape closes it.
        ->keys('add-person-name', 'Escape')
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
        "/host/{$event->id}/games",
        "/host/{$event->id}/logs",
        '/host/packs',
        '/host/trash',
        '/admin/users',
        '/admin/trash',
    ])->assertNoJavaScriptErrors();
});
