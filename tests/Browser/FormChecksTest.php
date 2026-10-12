<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\Event;
use App\Models\Person;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// U19: forms check their rules in the browser first and never send what the server would
// refuse for a simple reason. Every script returns at once (the plugin retries slow ones). The
// short waits after a submit stay: they give a request time to start, since the test proves
// that none does.
const COUNT_REQUESTS = "window.__sent = 0; up.on('up:request:load', (e) => { if (e.request.method !== 'GET') window.__sent++; });";

it('stops an empty Add names and an empty rename before sending', function (): void {
    $event = Event::factory()->create();
    Person::factory()->for($event)->create(['name' => 'Budi Santoso']);
    actingAs($event->owner()->firstOrFail());

    $page = visit("/host/{$event->id}/people")->assertScript(pageReady());
    $page->script(COUNT_REQUESTS);
    $page->script('(() => { [...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "Add names").click(); })()');
    $page->assertScript('!! document.querySelector("dialog[open] button[type=submit]")');
    // B-4: with nothing typed the Add button is off, so nothing can be sent.
    $page->script('[...document.querySelectorAll("dialog[open] button[type=submit]")].pop().click()');
    $page->wait(0.3);

    expect($page->script('window.__sent'))->toBe(0)
        ->and($page->script('[...document.querySelectorAll("dialog[open] button[type=submit]")].pop().disabled'))->toBeTrue();

    $page->script('document.querySelector("dialog[open]").close()');
    $page->script("Alpine.store('confirm').askRow({ dataset: { ask: 'rename' }, closest: () => document.querySelector('tr[data-row]') })");
    $page->assertScript('!! document.querySelector("dialog[open] #confirm-field")');
    $page->script('(() => { const f = document.querySelector("#confirm-field"); f.value = ""; f.dispatchEvent(new Event("input", { bubbles: true })); [...document.querySelectorAll("dialog[open] button[type=submit]")].pop().click(); })()');
    $page->wait(0.3);

    expect($page->script('window.__sent'))->toBe(0)
        ->and($page->script('document.querySelector("dialog[open] [data-check-error]")?.textContent'))->toBe('The name field is required.');
    $page->assertNoJavaScriptErrors();
});

it('keeps a custom time to vote inside 5 to 300 seconds before sending', function (): void {
    $host = User::factory()->create();
    $pack = new QuestionPack(['title' => 'Office Awards', 'game_type' => GameType::Pentahoot]);
    $pack->owner()->associate($host)->save();
    actingAs($host);

    $page = visit("/host/packs/{$pack->id}/questions/create")->assertScript(pageReady());
    $page->script(COUNT_REQUESTS);
    $page->script('(() => { document.querySelector("textarea[name=prompt]").value = "Most punctual?"; [...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "Custom").click(); })()');
    $page->assertScript('!! document.querySelector("input[name=duration_seconds]")?.offsetParent');
    $page->script('(() => { const s = document.querySelector("input[name=duration_seconds]"); s.value = "3"; s.dispatchEvent(new Event("input", { bubbles: true })); s.form.querySelector("button[type=submit]:not([data-move])") ; [...s.form.querySelectorAll("button[type=submit]")].pop().click(); })()');
    $page->wait(0.3);

    expect($page->script('window.__sent'))->toBe(0)
        ->and($page->script('document.querySelector("[data-check-error]")?.textContent'))->toBe('Any value from 5 to 300 seconds. Default 20.');
    $page->assertNoJavaScriptErrors();
});

it('turns Delete event on only once the name is typed exactly', function (): void {
    $event = Event::factory()->create(['name' => 'Gathering 2026']);
    actingAs($event->owner()->firstOrFail());

    $page = visit("/host/{$event->id}/settings")->assertScript(pageReady());
    $button = '[...document.querySelectorAll("form[action$=\'/'.$event->id.'\'] button[type=submit]")].pop().disabled';
    $type = fn (string $text): string => '(() => { const f = document.querySelector("input[name=confirm_name]"); f.value = '.json_encode($text).'; f.dispatchEvent(new Event("input", { bubbles: true })); })()';

    expect($page->script($button))->toBeTrue();
    $page->script($type('Gathering'));
    expect($page->script($button))->toBeTrue();
    $page->script($type('Gathering 2026'));
    expect($page->script($button))->toBeFalse();
    $page->assertNoJavaScriptErrors();
});
