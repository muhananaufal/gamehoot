<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

it('centres the logo and every icon in the narrowed sidebar (U1)', function (): void {
    $host = User::factory()->superAdmin()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Gathering']);
    actingAs($host);

    $page = visit('/host')->click('[aria-label="Collapse sidebar"]')->wait(0.5);

    // Largest distance in px between an icon's centre and the centre of the rail and of its own
    // highlight box; 1.5 px allows for sub-pixel rounding.
    $offset = $page->script(<<<'JS'
        (() => {
            const rail = document.querySelector('aside').getBoundingClientRect();
            const railCentre = rail.left + rail.width / 2;
            const centre = (el) => { const r = el.getBoundingClientRect(); return r.left + r.width / 2; };
            const logo = [...document.querySelectorAll('aside header svg, aside > div:first-child svg')].find((svg) => svg.getBoundingClientRect().width > 0);
            const gaps = [Math.abs(centre(logo) - railCentre)];
            for (const link of document.querySelectorAll('aside nav a')) {
                const icon = link.querySelector('svg, span[aria-hidden]');
                if (!icon || !link.getBoundingClientRect().width) continue;
                gaps.push(Math.abs(centre(icon) - railCentre), Math.abs(centre(icon) - centre(link)));
            }
            return Math.max(...gaps);
        })()
        JS);

    expect($offset)->toBeLessThanOrEqual(1.5);
    $page->assertNoJavaScriptErrors();
});

it('closes a dialog on a click outside its box and opens it again afterwards (U13)', function (): void {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['name' => 'Dina Host']);
    actingAs($admin);

    $page = visit('/admin/users')->wait(0.5);

    // Every script returns at once: the browser plugin retries slow ones (see MotionTest).
    // Disable sits in the row menu of the other account (C-7).
    $ask = <<<'JS'
        (() => {
            const button = [...document.querySelectorAll('[data-menu]')].find((b) => b.dataset.name === 'Dina Host');
            Alpine.store('rowMenu').openFrom(button);
            Alpine.store('confirm').askFor('disable', 'Dina Host', '/admin/users/x/status', 'x');
        })()
        JS;
    $dialog = 'document.querySelector(\'dialog[aria-labelledby="confirm-title"]\')';

    $page->script($ask);
    $page->wait(0.4);
    $opened = $page->script("{$dialog}.open");

    // The press and the click land on the <dialog> itself, i.e. its backdrop.
    $page->script("(() => { const d = {$dialog}; d.dispatchEvent(new PointerEvent('pointerdown', { bubbles: true })); d.dispatchEvent(new MouseEvent('click', { bubbles: true })); })()");
    $page->wait(0.4);
    $closed = ! $page->script("{$dialog}.open");

    $page->script($ask);
    $page->wait(0.4);

    expect([$opened, $closed, $page->script("{$dialog}.open")])->toBe([true, true, true]);
    $page->assertNoJavaScriptErrors();
});
