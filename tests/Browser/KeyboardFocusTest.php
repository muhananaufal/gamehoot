<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Found in the manual Claude-in-Chrome test after the final audit: the keyboard paths of FE-3
| (row menus) and FE-5 (Move up/down) were tested as JavaScript logic only, not in a rendered page.
*/

it('keeps the focus on a Move button after the new order is saved (FE-5, U9, WCAG 2.4.3)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create();
    foreach (['Alpha Awards', 'Beta Awards', 'Gamma Awards'] as $title) {
        Pentahoot::game($event, ['Most punctual?'], $title);
    }
    actingAs($host);

    $page = visit("/host/{$event->id}/games")->assertScript(pageReady());

    // A keyboard press on a focused button is a click on it.
    $page->script(<<<'JS'
        (() => {
            const button = [...document.querySelectorAll('button[data-move=up]')].find((b) => b.getAttribute('aria-label') === 'Move “Gamma Awards” up');
            button.focus();
            button.click();
        })()
        JS);

    // The order is saved 900 ms after the last press, then the list comes back from the server.
    $page->wait(3)
        ->assertScript("document.activeElement?.getAttribute('aria-label')", 'Move “Gamma Awards” up')
        ->assertScript("[...document.querySelectorAll('[data-id] a')].map((a) => a.textContent.trim()).filter((t) => t.endsWith('Awards')).slice(0, 3).join(',')", 'Alpha Awards,Gamma Awards,Beta Awards');

    expect($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Alpha Awards', 'Gamma Awards', 'Beta Awards']);
    $page->assertNoJavaScriptErrors();
});

it('moves the focus into a row menu, through it with the arrows and back with Escape (FE-3)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create();
    Person::factory()->for($event)->create(['name' => 'Rita Wulandari', 'name_normalized' => 'rita wulandari']);
    actingAs($host);

    $page = visit("/host/{$event->id}/people")->assertScript(pageReady());

    $page->script("(() => { const b = document.querySelector('tr[data-row] [data-menu]'); b.focus(); b.click(); })()");
    $page->wait(0.5)
        ->assertScript("document.activeElement?.getAttribute('role')", 'menuitem')
        ->assertScript('document.activeElement?.textContent.trim()', 'New link')
        ->assertScript("document.querySelector('tr[data-row] [data-menu]').getAttribute('aria-expanded')", 'true');

    $page->script("document.activeElement.dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', bubbles: true }))");
    $page->wait(0.2)->assertScript('document.activeElement?.textContent.trim()', 'Rename');

    $page->script("window.dispatchEvent(new KeyboardEvent('keydown', { key: 'Escape', bubbles: true }))");
    $page->wait(0.3)
        ->assertScript("document.activeElement === document.querySelector('tr[data-row] [data-menu]')", true)
        ->assertScript("document.querySelector('tr[data-row] [data-menu]').getAttribute('aria-expanded')", 'false');
    $page->assertNoJavaScriptErrors();
});

it('keeps the focus on the moved question in a pack, whose number changes with the move (U9, WCAG 2.4.3)', function (): void {
    $host = User::factory()->create();
    $pack = Pentahoot::pack($host, ['First prompt?', 'Second prompt?', 'Third prompt?']);
    actingAs($host);

    $page = visit("/host/packs/{$pack->id}")->assertScript(pageReady());

    $page->script(<<<'JS'
        (() => {
            const button = [...document.querySelectorAll('button[data-move=up]')].find((b) => b.getAttribute('aria-label') === 'Move question 3 up');
            button.focus();
            button.click();
        })()
        JS);

    // The third question is now the second, so the focused button reads "Move question 2 up".
    $page->wait(3)->assertScript("document.activeElement?.getAttribute('aria-label')", 'Move question 2 up');

    expect($pack->questions()->with('pentahoot')->orderBy('position')->get()->map(fn ($question) => $question->pentahoot?->prompt)->all())
        ->toBe(['First prompt?', 'Third prompt?', 'Second prompt?']);
    $page->assertNoJavaScriptErrors();
});
