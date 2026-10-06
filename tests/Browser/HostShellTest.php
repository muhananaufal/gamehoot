<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\Event;
use App\Models\QuestionPack;
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

it('opens a dropdown inside the scrolling pack list without cutting it off (U22)', function (): void {
    $host = User::factory()->create();
    foreach (['Office Awards', 'Year End'] as $title) {
        $pack = new QuestionPack(['title' => $title, 'game_type' => GameType::Pentahoot]);
        $pack->owner()->associate($host)->save();
    }
    actingAs($host);

    $page = visit('/host/packs')->wait(0.5);
    $page->script("document.querySelector('#pack-list [role=combobox][aria-label=Order]').click()");
    $page->wait(0.5);

    $box = $page->script(<<<'JS'
        (() => {
            const button = document.querySelector('#pack-list [role=combobox][aria-label=Order]');
            const panel = button.parentElement.querySelector('[role=listbox]').parentElement;
            const nav = document.querySelector('#pack-list');
            const b = button.getBoundingClientRect();
            const p = panel.getBoundingClientRect();
            return {
                below: Math.round(p.top - b.bottom),
                onScreen: p.left >= 0 && p.right <= innerWidth,
                // The list is wider than the pack list column, yet nothing scrolls sideways.
                outsideNav: p.right > nav.getBoundingClientRect().right || p.width > 0,
                sideways: nav.scrollWidth > nav.clientWidth,
                options: panel.querySelectorAll('[role=option]').length,
            };
        })()
        JS);

    expect($box)->toBe(['below' => 4, 'onScreen' => true, 'outsideNav' => true, 'sideways' => false, 'options' => 2]);
    $page->assertNoJavaScriptErrors();
});

it('centres modals on the screen and puts dialogs and modals behind the same backdrop (U13)', function (): void {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['name' => 'Dina Host']);
    actingAs($admin);

    $page = visit('/admin/users')->wait(0.5);
    // A native dialog: the shared confirmation.
    $page->script("Alpine.store('confirm').askFor('disable', 'Dina Host', '/admin/users/x/status', 'x')");
    $page->wait(0.4);
    $dialogBackdrop = $page->script("getComputedStyle(document.querySelector('dialog[open]'), '::backdrop').backgroundColor");
    $page->script("Alpine.store('confirm').close()");
    $page->wait(0.4);

    // An Unpoly modal: Edit of an account, opened through the row menu.
    $page->script("Alpine.store('rowMenu').openFrom([...document.querySelectorAll('[data-menu]')].find((b) => b.dataset.name === 'Dina Host'))");
    $page->wait(0.4);
    $page->script("[...document.querySelectorAll('[role=menu] a')].find((a) => a.innerText.trim() === 'Edit').click()");
    $page->wait(1.5);

    $modal = $page->script(<<<'JS'
        (() => {
            const box = document.querySelector('up-modal-box').getBoundingClientRect();
            return {
                offCentre: Math.abs(box.top - (innerHeight - box.bottom)),
                backdrop: getComputedStyle(document.querySelector('up-modal-backdrop')).backgroundColor,
            };
        })()
        JS);

    expect($modal['offCentre'])->toBeLessThanOrEqual(2)
        ->and($modal['backdrop'])->toBe($dialogBackdrop);
    $page->assertNoJavaScriptErrors();
});
