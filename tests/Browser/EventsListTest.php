<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('filters events by a range picked in the calendar, quickly and from the keyboard too (U15)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Early Party', 'created_at' => CarbonImmutable::now()->subDays(40)]);
    Event::factory()->for($host, 'owner')->create(['name' => 'Fresh Party', 'created_at' => CarbonImmutable::now()]);
    actingAs($host);

    // The button opens a modal; a retried click would hit it behind the modal
    // (docs/debugging/2026-10-02-browser-click-timeout-after-navigation.md).
    $page = visit('/host');
    $page->script('document.querySelector("[aria-label=\'Filter by created date\']").click()');
    $page->wait(0.3);

    // Opening puts the keyboard on a day of the grid (W3C APG).
    $page->assertScript('document.activeElement?.dataset.day?.length === 10');

    // Each step of the calendar stays well under half a second; the first design took about a
    // second per click because every day re-ran its bindings.
    $slowest = $page->script(<<<'JS'
        (async () => {
            const p = Alpine.$data(document.querySelector('[x-data^=dateRangePicker]'));
            const time = async (fn) => { const t = performance.now(); fn(); await new Promise((r) => setTimeout(r, 0)); return performance.now() - t; };
            const steps = [
                await time(() => p.shift(-1)),
                await time(() => p.shift(1)),
                await time(() => p.preset('last7')),
            ];
            return Math.max(...steps);
        })()
        JS);
    expect($slowest)->toBeLessThan(500);

    $visible = 'JSON.stringify([...document.querySelectorAll("tr[data-row]")].filter((row) => ! row.hidden).map((row) => row.querySelector("a").innerText))';

    $page->click('Apply')
        ->assertScript($visible, '["Fresh Party"]')
        ->click('Reset filters')
        ->assertScript($visible, '["Fresh Party","Early Party"]')
        ->assertNoJavaScriptErrors();
});
