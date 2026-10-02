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
