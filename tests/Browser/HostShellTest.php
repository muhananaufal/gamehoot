<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\Event;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

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

    $modal = (array) $page->script(<<<'JS'
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

it('expands a recent event in place and stays on the page (U23)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    actingAs($host);

    $page = visit('/host/packs')->wait(0.5);
    $settings = '[...document.querySelectorAll("#host-nav a")].find((a) => a.innerText.trim() === "Settings")';

    expect($page->script("{$settings}?.offsetParent === null"))->toBeTrue();
    $page->script('document.querySelector("#host-nav button[title=\'Gathering 2026\']").click()');
    $page->wait(0.3);

    expect($page->script("{$settings}?.offsetParent !== null"))->toBeTrue()
        ->and($page->script('location.pathname'))->toBe('/host/packs');
    $page->assertNoJavaScriptErrors();
});

it('adds several games from the Add games dialog, in the order picked (D-9)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    foreach (['Office Awards', 'Team Trivia'] as $title) {
        $pack = new QuestionPack(['title' => $title, 'game_type' => GameType::Pentahoot]);
        $pack->owner()->associate($host)->save();
    }
    actingAs($host);

    $page = visit("/host/{$event->id}/games")->wait(0.5);
    $page->script('[...document.querySelectorAll("button")].find((b) => b.innerText.trim() === "Add game").click()');
    $page->wait(0.3);
    // Team Trivia first, then Office Awards: the games follow the order picked.
    $page->script('(() => { const packs = [...document.querySelectorAll("dialog[open] [data-pack]")]; packs.find((p) => p.dataset.title === "Team Trivia").click(); packs.find((p) => p.dataset.title === "Office Awards").click(); })()');
    $page->wait(0.3);

    expect($page->script('document.querySelector("[data-test=add-games]").innerText.trim()'))->toBe('Add 2 games');

    $page->script('document.querySelector("[data-test=add-games]").click()');
    $page->wait(3);

    expect($event->games()->orderBy('position')->pluck('title')->all())->toBe(['Team Trivia', 'Office Awards']);
    $page->assertNoJavaScriptErrors();
});

it('lines the Games page up with Question packs on a wide phone (D-9)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    $event->games()->create(['type' => GameType::Pentahoot, 'title' => 'Office Awards', 'position' => 1]);
    $pack = new QuestionPack(['title' => 'Office Awards', 'game_type' => GameType::Pentahoot]);
    $pack->owner()->associate($host)->save();
    actingAs($host);

    // Both lists fill their column; nothing in a flex column shrinks them to their content.
    $width = fn (string $selector): string => 'Math.round(document.querySelector("'.$selector.'").getBoundingClientRect().width)';
    $games = visit("/host/{$event->id}/games")->resize(700, 900)->wait(0.5)->script($width('#games-list'));
    $packs = visit('/host/packs')->resize(700, 900)->wait(0.5)->script($width('#pack-list'));

    expect($games)->toBe($packs);
});

it('moves a question of a game with the arrows and saves the order once (D-9)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    $game = Pentahoot::game($event, ['First?', 'Second?']);
    actingAs($host);

    $page = visit("/host/{$event->id}/games/{$game->id}")->wait(0.5);
    $page->script('document.querySelector("#game-panel [data-move=down]").click()');
    $page->wait(3);

    expect($game->questions()->orderBy('position')->with('pentahoot')->get()->map(fn ($question) => $question->pentahoot?->prompt)->all())->toBe(['Second?', 'First?']);
    $page->assertNoJavaScriptErrors();
});

it('follows a link whose query ends in .csv inside the page, and leaves real downloads to the browser (U18, B-4)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    actingAs($host);

    $page = visit("/host/{$event->id}/people")->wait(0.5);
    $followable = fn (string $attributes): mixed => $page->script('(() => { const a = document.createElement("a"); '.$attributes.' document.querySelector("[data-host-shell] main").appendChild(a); const followed = window.up.link.isFollowable(a); a.remove(); return followed; })()');

    // Back from the import review names the last file, e.g. ?last=staff.csv: it stays in the modal.
    expect($followable('a.href = "/host/'.$event->id.'/people/import?last=staff.csv";'))->toBeTrue()
        ->and($followable('a.href = "/host/'.$event->id.'/results.csv"; a.setAttribute("download", "");'))->toBeFalse();
    $page->assertNoJavaScriptErrors();
});

it('keeps a refused upload in the Import modal, with its error under the file field and one page inside (B-4)', function (): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);
    actingAs($host);

    $page = visit("/host/{$event->id}/people")->wait(0.5);
    $page->script('[...document.querySelectorAll("a")].find((a) => a.innerText.trim() === "Import CSV").click()');
    $page->assertScript('!! document.querySelector("up-modal #import-file")');
    // Nothing picked: the server refuses, and the modal shows the Upload step again.
    $page->script('[...document.querySelectorAll("up-modal button[type=submit]")].pop().click()');
    $page->assertScript('document.querySelector("up-modal")?.innerText.includes("Pick a CSV file first.")');
    $page->wait(1);

    expect($page->script('window.up.layer.count'))->toBe(2)
        ->and($page->script('document.querySelectorAll("up-modal [up-main]").length'))->toBe(1)
        ->and($page->script('document.querySelectorAll("up-bounds").length'))->toBe(0)
        // U13: no close button; Cancel, Escape and a click outside close the modal.
        ->and($page->script('!! document.querySelector("up-modal up-modal-dismiss")'))->toBeFalse();
    $page->assertNoJavaScriptErrors();
});

it('keeps the name and role of the signed-in host readable in the sidebar (U1)', function (): void {
    actingAs(User::factory()->create(['name' => 'Rita Wulandari']));

    $page = visit('/host')->wait(1);

    $page->assertScript("document.querySelector('[data-test=account-name]').getBoundingClientRect().width > 100", true)
        ->assertScript("document.querySelector('[data-test=account-name]').scrollWidth <= document.querySelector('[data-test=account-name]').clientWidth", true)
        ->assertNoJavaScriptErrors();
});

it('shows the tooltip when the pointer crosses onto the icon or slides to the next button (U33)', function (): void {
    actingAs(User::factory()->create());

    $page = visit('/host')->wait(1);
    // The real pointer path: over the button's edge, onto its icon, then on to the next button.
    $page->script(<<<'JS'
        (() => {
            const theme = document.querySelector('#host-sidebar [aria-label="Switch theme"]');
            const motion = document.querySelector('#host-sidebar [x-data] [aria-label="Reduce motion"], #host-sidebar button[aria-label="Reduce motion"]');
            const icon = theme.querySelector('svg, i, span') ?? theme;
            const fire = (type, target, relatedTarget) => target.dispatchEvent(new PointerEvent(type, { bubbles: true, relatedTarget }));
            fire('pointerover', theme, null);
            setTimeout(() => { fire('pointerout', theme, icon); fire('pointerover', icon, theme); }, 50);
            setTimeout(() => { window.__first = document.getElementById('host-tip')?.hidden === false ? document.getElementById('host-tip').textContent : null; }, 600);
            setTimeout(() => { fire('pointerout', icon, motion); fire('pointerover', motion, icon); }, 700);
            setTimeout(() => { window.__second = document.getElementById('host-tip')?.hidden === false ? document.getElementById('host-tip').textContent : null; }, 1300);
        })()
        JS);
    $page->wait(1.8);

    $page->assertScript('window.__first', 'Switch theme')
        ->assertScript('window.__second', 'Reduce motion')
        ->assertNoJavaScriptErrors();
});
