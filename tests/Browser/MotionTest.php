<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// Every script() returns at once: the browser plugin retries a script that takes over a second
// (docs/debugging/2026-10-02-browser-click-timeout-after-navigation.md). A recorder in the page
// samples the motion while PHP waits; those waits measure the motion and stay. Waits before an
// assertion that retries by itself are gone (tests/Pest.php).

it('slides table rows to their new place when the list is sorted, then settles (U8)', function (): void {
    $host = User::factory()->create();
    foreach (['Alpha Night', 'Beta Day', 'Gamma Party'] as $index => $name) {
        Event::factory()->for($host, 'owner')->create(['name' => $name, 'created_at' => now()->subDays($index)]);
    }
    actingAs($host);

    $page = visit('/host')->assertScript(pageReady());
    // Sort by name, then by name again (Z-A): every row changes place.
    $page->script("document.querySelector('thead th:first-child button').click()");
    $page->wait(1);
    $page->script(<<<'JS'
        (() => {
            window.__most = 0;
            const rows = () => [...document.querySelectorAll('tbody tr[data-row]:not([hidden])')];
            const moving = () => rows().filter((row) => !['none', 'matrix(1, 0, 0, 1, 0, 0)'].includes(getComputedStyle(row).transform)).length;
            window.__moving = moving;
            const timer = setInterval(() => (window.__most = Math.max(window.__most, moving())), 20);
            setTimeout(() => clearInterval(timer), 600);
            document.querySelector('thead th:first-child button').click();
        })()
        JS);
    $page->wait(1.5);

    expect($page->script('window.__most'))->toBeGreaterThan(0)
        ->and($page->script('window.__moving()'))->toBe(0)
        ->and($page->script("[...document.querySelectorAll('tbody tr[data-row]:not([hidden])')].map((row) => row.dataset.sortName)"))
        ->toBe(['Gamma Party', 'Beta Day', 'Alpha Night']);
    $page->assertNoJavaScriptErrors();
});

it('pops a modal in, fully visible once it settles (U8)', function (): void {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['name' => 'Dina Host']);
    actingAs($admin);

    // Edit of an account is a modal (C-7, U13): open it through the row menu.
    $page = visit('/admin/users')->assertScript(pageReady());
    $page->script("Alpine.store('rowMenu').openFrom([...document.querySelectorAll('[data-menu]')].find((b) => b.dataset.name === 'Dina Host'))");
    $page->assertScript("[...document.querySelectorAll('[role=menu] a')].some((a) => a.innerText.trim() === 'Edit')", true);
    $page->script(<<<'JS'
        (() => {
            window.__opacities = [];
            const timer = setInterval(() => {
                const box = document.querySelector('up-modal-box');
                if (box) window.__opacities.push(Number(getComputedStyle(box).opacity));
            }, 15);
            setTimeout(() => clearInterval(timer), 1500);
            [...document.querySelectorAll('[role=menu] a')].find((a) => a.innerText.trim() === 'Edit').click();
        })()
        JS);
    $page->wait(2);

    $sampled = $page->script('window.__opacities');
    $opacities = array_map(fn (mixed $value): float => is_numeric($value) ? (float) $value : -1.0, is_array($sampled) ? array_values($sampled) : []);
    expect($opacities)->not->toBeEmpty()
        ->and($opacities === [] ? 1.0 : min($opacities))->toBeLessThan(1)
        ->and($opacities === [] ? 0.0 : $opacities[count($opacities) - 1])->toBe(1.0);
    $page->assertNoJavaScriptErrors();
});

it('names an icon button in a Pentahoot tooltip on hover, and Escape closes it in place (U33)', function (): void {
    actingAs(User::factory()->create());

    $page = visit('/host')->assertScript(pageReady());
    $page->hover('[aria-label="Switch theme"]');

    $page->assertScript("document.getElementById('host-tip')?.hidden === false && document.getElementById('host-tip').textContent", 'Switch theme')
        // The browser's own title no longer shows beside it.
        ->assertScript("document.querySelector('[aria-label=\"Switch theme\"]').hasAttribute('title')", false);

    $page->keys('[aria-label="Switch theme"]', 'Escape');

    $page->assertScript("document.getElementById('host-tip').hidden", true)
        ->assertScript("document.activeElement?.getAttribute('aria-label')", 'Switch theme')
        ->assertNoJavaScriptErrors();
});

it('lets Penta cover its eyes for the password and peek when it is shown (U32)', function (): void {
    $page = visit('/login')->assertScript(pageReady());

    $page->type('password', 'abc');
    $page->assertScript("document.querySelector('[data-mood]').dataset.mood", 'shy');

    $page->click('[aria-label="Show password"]');
    $page->assertScript("document.querySelector('[data-mood]').dataset.mood", 'peek')
        ->assertNoJavaScriptErrors();
});

it('greets the lobby with Penta and celebrates on a canvas that clears itself (U30, U31)', function (): void {
    Event::factory()->open()->create(['slug' => 'gathering-2026']);

    $page = visit('/gathering-2026/screen');
    $page->assertScript("getComputedStyle(document.querySelector('.scene-stack')).display", 'grid')
        ->assertScript("document.querySelector('.scene-stack [data-mood]')?.dataset.mood", 'wave');

    $page->script("window.dispatchEvent(new CustomEvent('celebrate', { detail: { key: 'browser-test' } }))");
    $page->assertScript("!!document.querySelector('[x-ref=confetti] canvas')", true);

    // The canvas removes itself after the burst; the assertion waits for that.
    $page->assertScript("!!document.querySelector('[x-ref=confetti] canvas')", false)
        ->assertNoJavaScriptErrors();
});
