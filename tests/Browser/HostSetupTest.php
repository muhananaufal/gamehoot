<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\Event;
use App\Models\Person;
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

    // U13, U26: New event opens a dialog already in the page; once created, the host goes on in
    // Live control. Clicks that open or send a dialog go through a script, so the browser plugin
    // cannot retry them (docs/debugging/2026-10-02-browser-click-timeout-after-navigation.md).
    $page = visit('/host')->assertSee('No events yet.');
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "New event").click()');
    $page->assertScript('!! document.querySelector("dialog[open] form")')
        ->assertPathEndsWith('/host')
        ->type('name', 'Gathering 2026');

    // U13, U19: the link follows the name; another event uses it, and the server says so once the
    // name is done. Clearing the link lets the server pick a free one (F10).
    expect($page->script('document.querySelector("dialog[open] input[name=slug]").value'))->toBe('gathering-2026');
    $page->script('document.querySelector("dialog[open] input[name=name]").dispatchEvent(new Event("change", { bubbles: true }))');
    $page->assertSee('Another event already uses this link.');
    $page->script('(() => { const slug = document.querySelector("dialog[open] input[name=slug]"); slug.value = ""; slug.dispatchEvent(new Event("input", { bubbles: true })); })()');

    // U26: Next, then Skip names and games, then Create event on the review.
    $step = fn (string $test): string => '[...document.querySelectorAll("dialog[open] [data-test='.$test.']")].find((b) => b.offsetParent !== null).click()';
    $page->script($step('wizard-next'));
    $page->assertScript('document.querySelector("dialog[open] [data-step=names]").getAttribute("aria-current") === "step"');
    // Wait for each step to be drawn before the next click: a click in the same moment could reach the button of the step before.
    $page->assertScript('document.querySelector("dialog[open] [data-step=names]").getAttribute("aria-current") === "step" && [...document.querySelectorAll("dialog[open] [data-test=wizard-next]")].filter((b) => b.offsetParent !== null).map((b) => b.innerText.trim()).join() === "Skip"');
    $page->script($step('wizard-next'));
    $page->assertScript('document.querySelector("dialog[open] [data-step=games]").getAttribute("aria-current") === "step" && [...document.querySelectorAll("dialog[open] [data-test=wizard-next]")].filter((b) => b.offsetParent !== null).map((b) => b.innerText.trim()).join() === "Skip"');
    $page->script($step('wizard-next'));
    $page->assertSee('Nothing is saved until you press Create event.');
    $page->script($step('wizard-create'));

    $page->assertSee('Event created. Open it when you are ready, and players can join.')
        ->assertSee('Get ready')
        ->assertScript('! document.querySelector("dialog[open]")')
        ->assertNoJavaScriptErrors();
    $event = Event::query()->where('owner_id', $host->id)->sole();
    $page = visit("/host/{$event->id}/people")->assertSee('Add names');

    // U2, B-4: Add names opens a dialog in the content header; one name per line.
    $type = fn (array $names): string => '(() => { const field = document.querySelector("dialog[open] #add-names"); field.value = '.json_encode($names).'.join(String.fromCharCode(10)); field.dispatchEvent(new Event("input", { bubbles: true })); })()';
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "Add names").click()');
    $page->assertScript('!! document.querySelector("dialog[open] #add-names")');
    $page->script($type(['Budi Santoso', 'Rita Wulandari']));
    expect($page->script('[...document.querySelectorAll("dialog[open] button[type=submit]")].pop().innerText.trim()'))->toBe('Add 2 names');
    $page->script('[...document.querySelectorAll("dialog[open] button[type=submit]")].pop().click()');
    $page->assertSee('2 names added.');

    // A name already in the event is caught while typing; the button stays off.
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "Add names").click()');
    $page->assertScript('!! document.querySelector("dialog[open] #add-names")');
    $page->script($type(['budi  SANTOSO']));
    $page->assertSee('Line 1: Same as “Budi Santoso” already in this event');
    expect($page->script('[...document.querySelectorAll("dialog[open] button[type=submit]")].pop().disabled'))->toBeTrue();

    // Escape closes the dialog. The CSV upload itself is covered by feature tests: the browser
    // plugin's test server does not forward multipart bodies (W11).
    // Import CSV loads a modal from the server (U18): a plugin click that is retried after it worked
    // lands on that modal and times out (Safari, gate run 37895969265), so it goes through a script.
    $page->keys('add-names', 'Escape');
    $page->assertScript('! document.querySelector("dialog[open]")');
    $page->script('[...document.querySelectorAll("a")].find((a) => a.innerText.trim() === "Import CSV" && a.offsetParent !== null).click()');
    $page->assertSee('CSV file')
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

it('fills the link from the event name and stops an empty name before sending (U13, U19)', function (): void {
    $host = User::factory()->create();
    actingAs($host);

    $page = visit('/host')->assertScript(pageReady());
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "New event").click()');
    $page->type('name', 'Year End Party');

    expect($page->script('document.querySelector("dialog[open] input[name=slug]").value'))->toBe('year-end-party');

    // An empty name: Next stays on step 1, sends nothing and says why under the field (U26).
    $page->script('(() => { const d = document.querySelector("dialog[open]"); d.querySelector("input[name=name]").value = ""; window.__sent = false; window.addEventListener("up:request:load", () => (window.__sent = true)); [...d.querySelectorAll("[data-test=wizard-next]")].find((b) => b.offsetParent !== null).click(); })()');
    // Proves that nothing is sent, so it gives a request time to start instead of a condition.
    $page->wait(0.4);

    expect($page->script('window.__sent'))->toBeFalse()
        ->and($page->script('document.querySelector("dialog[open] [data-check-error]")?.textContent'))->toBe('The event name field is required.')
        ->and($page->script('document.querySelector("dialog[open] [data-step=details]").getAttribute("aria-current")'))->toBe('step')
        ->and($page->script('document.querySelector("dialog[open] [data-step=review]").disabled'))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});

it('sets up a new event with names and games in four steps, saved at the last one (U26)', function (): void {
    $host = User::factory()->create();
    foreach (['Office Awards', 'Team Trivia'] as $title) {
        $pack = new QuestionPack(['title' => $title, 'game_type' => GameType::Pentahoot]);
        $pack->owner()->associate($host)->save();
    }
    actingAs($host);

    $page = visit('/host')->assertScript(pageReady());
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "New event").click()');
    $page->type('name', 'Year End Party');
    $click = fn (string $selector): string => '[...document.querySelectorAll("dialog[open] '.$selector.'")].find((b) => b.offsetParent !== null).click()';
    $page->script($click('[data-test=wizard-next]'));

    // Names: one per line; a repeat holds Next back until it is fixed.
    $type = fn (array $names): string => '(() => { const t = document.querySelector("dialog[open] #new-event-names"); t.value = '.json_encode($names).'.join(String.fromCharCode(10)); t.dispatchEvent(new Event("input", { bubbles: true })); })()';
    $page->script($type(['Rita Wulandari', 'rita wulandari']));
    $page->assertSee('Line 2: Same as line 1.');
    expect($page->script('[...document.querySelectorAll("dialog[open] [data-test=wizard-next]")].find((b) => b.offsetParent !== null).disabled'))->toBeTrue();
    $page->script($type(['Rita Wulandari', 'Budi Santoso']));
    $page->script($click('[data-test=wizard-next]'));

    // Games: Team Trivia first, then Office Awards.
    $page->script('(() => { const packs = [...document.querySelectorAll("dialog[open] [data-pack]")]; packs.find((p) => p.dataset.title === "Team Trivia").click(); packs.find((p) => p.dataset.title === "Office Awards").click(); })()');
    $page->script($click('[data-test=wizard-next]'));

    // Review: everything that Create event saves; Edit goes back without losing anything.
    $page->assertSee('2 names')->assertSee('1. Team Trivia · 2. Office Awards');
    $page->script($click('[data-step=names]'));
    expect($page->script('document.querySelector("dialog[open] #new-event-names").value.split(String.fromCharCode(10))'))->toBe(['Rita Wulandari', 'Budi Santoso']);
    $page->script($click('[data-step=review]'));
    $page->script($click('[data-test=wizard-create]'));

    $page->assertSee('Event created. Open it when you are ready, and players can join.')->assertNoJavaScriptErrors();
    $event = Event::query()->where('owner_id', $host->id)->sole();
    expect($event->people()->orderBy('name')->pluck('name')->all())->toBe(['Budi Santoso', 'Rita Wulandari'])
        ->and($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Team Trivia', 'Office Awards']);
});

it('asks before New event drops what was entered (U24, U26)', function (): void {
    actingAs(User::factory()->create());

    $page = visit('/host')->assertScript(pageReady());
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "New event").click()');
    $page->type('name', 'Year End Party');
    $page->keys('name', 'Escape');
    // The question is its own <dialog> inside the wizard dialog, so its own open state counts.
    $asking = '[...document.querySelectorAll("h2")].some((h) => h.textContent.trim() === "Discard this event?" && h.closest("dialog").open)';
    $page->assertScript($asking, true);

    $page->script('[...document.querySelectorAll("dialog[open] button")].find((b) => b.innerText.trim() === "Keep editing").click()');
    $page->assertScript($asking, false);
    expect($page->script('document.querySelector("dialog[open] input[name=name]").value'))->toBe('Year End Party');

    $page->script('[...document.querySelectorAll("dialog[open] button")].find((b) => b.innerText.trim() === "Cancel" && b.offsetParent !== null).click()');
    $page->assertScript($asking, true);
    $page->script('[...document.querySelectorAll("dialog[open] button")].find((b) => b.innerText.trim() === "Discard").click()');

    $page->assertScript('document.querySelectorAll("dialog[open]").length === 0')
        ->assertNoJavaScriptErrors();
});

it('goes to a typed page of a long list, with first, last and neighbours numbered (U4)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    Person::factory()->for($event)->count(100)->create();
    actingAs($host);

    $page = visit("/host/{$event->id}/people")->assertScript(pageReady());
    // 10 rows per page: 10 pages, so the page number field shows.
    $page->script('Alpine.$data(document.querySelector("[x-data^=tableView]")).size = 10');
    $page->assertScript('!! document.querySelector("input[type=number][placeholder]")');
    $page->script('(() => { const field = document.querySelector("input[type=number][placeholder]"); field.value = "6"; field.dispatchEvent(new KeyboardEvent("keydown", { key: "Enter", bubbles: true })); })()');
    $page->assertScript('[...document.querySelector("nav[aria-label=Pages]").querySelectorAll("[aria-label^=Page], span[aria-hidden]")].map((e) => e.innerText).join(" ")', '1 … 5 6 7 … 10');

    expect($page->script('document.querySelector("nav[aria-label=Pages] [aria-current=page]").innerText'))->toBe('6')
        ->and($page->script('[...document.querySelectorAll("p[aria-live=polite]")].map((p) => p.innerText).find((t) => t.startsWith("Showing"))'))->toBe('Showing 51–60 of 100');
    $page->assertNoJavaScriptErrors();
});

it('keeps every field of New event while the host changes others and moves between steps (U26, U19)', function (): void {
    actingAs(User::factory()->create());

    $page = visit('/host')->assertScript(pageReady());
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "New event").click()');
    $page->type('name', 'Year End Party');
    // Another field changes: the live check of the server draws the fields again (U19).
    $page->script('(() => { const d = document.querySelector("dialog[open]"); const slug = d.querySelector("input[name=slug]"); slug.value = "year-end-2026"; slug.dispatchEvent(new Event("input", { bubbles: true })); slug.dispatchEvent(new Event("change", { bubbles: true })); d.querySelector("input[name=name]").dispatchEvent(new Event("change", { bubbles: true })); })()');
    // The live check answers from the server and draws the fields again; nothing on the page
    // marks the end of that redraw, so this stays a fixed wait.
    $page->wait(3);
    $page->script('[...document.querySelectorAll("dialog[open] label")].find((l) => l.innerText.trim() === "Light")?.click()');
    $page->script('[...document.querySelectorAll("dialog[open] [data-test=wizard-next]")].find((b) => b.offsetParent !== null).click()');
    $page->script('[...document.querySelectorAll("dialog[open] [data-step=details]")][0].click()');
    $page->assertScript('document.querySelector("dialog[open] [data-step=details]").getAttribute("aria-current")', 'step');

    expect($page->script('document.querySelector("dialog[open] input[name=name]").value'))->toBe('Year End Party')
        ->and($page->script('document.querySelector("dialog[open] input[name=slug]").value'))->toBe('year-end-2026');

    // A valid change on step 1 keeps the other steps open; an empty name closes them.
    $page->script('[...document.querySelectorAll("dialog[open] label")].find((l) => l.innerText.trim() === "Dark")?.click()');
    expect($page->script('document.querySelector("dialog[open] [data-step=review]").disabled'))->toBeFalse();
    $page->script('(() => { const n = document.querySelector("dialog[open] input[name=name]"); n.value = ""; n.dispatchEvent(new Event("input", { bubbles: true })); })()');
    expect($page->script('document.querySelector("dialog[open] [data-step=review]").disabled'))->toBeTrue();
    $page->assertNoJavaScriptErrors();
});
