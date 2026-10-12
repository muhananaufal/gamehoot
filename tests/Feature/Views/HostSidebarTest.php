<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

describe('U1 host sidebar', function (): void {
    it('lists the five events the host changed last, own and co-hosted, and nobody else\'s', function (): void {
        $host = User::factory()->create();
        foreach (range(1, 6) as $day) {
            travelTo(now()->setDate(2026, 10, $day));
            Event::factory()->for($host, 'owner')->create(['name' => "Own event {$day}"]);
        }
        travelTo(now()->setDate(2026, 10, 7));
        $cohosted = Event::factory()->create(['name' => 'Cohosted gathering']);
        $cohosted->cohosts()->attach($host);
        Event::factory()->create(['name' => 'Someone else party']);

        actingAs($host)->get('/host/packs')
            ->assertOk()
            ->assertSeeInOrder(['Recent events', 'Cohosted gathering', 'Own event 6', 'Own event 5', 'Own event 4', 'Own event 3'])
            ->assertDontSee('Own event 2')
            ->assertDontSee('Someone else party');
    });

    it('keeps the Reduce motion switch beside the theme switch at the foot of the sidebar (U27)', function (): void {
        actingAs(User::factory()->create())->get('/host/packs')
            ->assertOk()
            ->assertSeeInOrder(['aria-label="Switch theme"', '$store.motion.toggle()', 'aria-label="Log out"'], false);
    });

    it('opens the pages of the event being worked on under its name', function (): void {
        $host = User::factory()->create();
        $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);

        actingAs($host)->get("/host/{$event->id}/settings")
            ->assertOk()
            ->assertSee('href="'.route('host.events.people.index', $event).'"', false)
            ->assertSee('aria-current="page"', false);
    });

    it('expands a recent event in place instead of opening it, so its pages are one click away (U23)', function (): void {
        $host = User::factory()->create();
        $event = Event::factory()->for($host, 'owner')->create(['name' => 'Gathering 2026']);

        $html = (string) actingAs($host)->get('/host/packs')->assertOk()->getContent();

        expect($html)->toMatch('/<button type="button"[^>]*aria-expanded="false"[^>]*title="Gathering 2026"/')
            ->and($html)->not->toContain('href="'.route('host.events.live', $event).'" title="Gathering 2026"')
            ->and($html)->toContain('href="'.route('host.events.edit', $event).'"');

        // The event being worked on starts expanded.
        $current = (string) actingAs($host)->get("/host/{$event->id}/people")->getContent();
        expect($current)->toMatch('/<button type="button"[^>]*aria-expanded="true"[^>]*title="Gathering 2026"/');
    });

    it('colours a recent event like everywhere else: open green, finished yellow, draft grey (D-10)', function (): void {
        $host = User::factory()->create();
        foreach ([['Open party', EventStatus::Open], ['Closed party', EventStatus::Finished], ['Draft party', EventStatus::Draft]] as [$name, $status]) {
            Event::factory()->for($host, 'owner')->create(['name' => $name, 'status' => $status]);
        }

        $closed = Event::query()->where('name', 'Closed party')->sole();
        $html = (string) actingAs($host)->get("/host/{$closed->id}/results")->getContent();
        $dot = fn (string $name): string => (string) Str::of($html)->after('title="'.$name.'"')->match('/size-2 rounded-full ([a-z-]+)/');

        expect($dot('Open party'))->toBe('bg-success-dot')
            ->and($dot('Closed party'))->toBe('bg-warning-dot')
            ->and($dot('Draft party'))->toBe('bg-nav-icon');
    });

    it('leaves finished events out of the recent ones, unless one is open on screen (D-10)', function (): void {
        $host = User::factory()->create();
        Event::factory()->for($host, 'owner')->create(['name' => 'Open party', 'status' => EventStatus::Open]);
        $closed = Event::factory()->for($host, 'owner')->create(['name' => 'Closed party', 'status' => EventStatus::Finished]);

        actingAs($host)->get('/host/packs')->assertSee('title="Open party"', false)->assertDontSee('title="Closed party"', false);
        actingAs($host)->get("/host/{$closed->id}/results")->assertSee('title="Closed party"', false);
    });

    it('scrolls on its own, apart from the page, and keeps theme and sign out in view', function (): void {
        $host = User::factory()->create();

        $html = (string) actingAs($host)->get('/host')->getContent();

        expect($html)->toMatch('/<aside id="host-sidebar"[^>]*lg:sticky[^>]*lg:h-dvh/')
            ->and($html)->toMatch('/<nav [^>]*class="[^"]*overflow-y-auto[^"]*" aria-label="Main"/')
            // U32: the phone drawer slides in with the motion tokens, not a fixed 200 ms.
            ->and($html)->toMatch('/<aside id="host-sidebar"[^>]*class="[^"]* drawer /')
            ->and($html)->not->toMatch('/<aside id="host-sidebar"[^>]*duration-200/');
    });

    it('can be narrowed to icons on wide screens, with a labelled button', function (): void {
        $host = User::factory()->create();

        actingAs($host)->get('/host')
            ->assertSee('aria-label="Collapse sidebar"', false)
            // U33: a narrowed sidebar names its links in a Pentahoot tooltip to their right.
            ->assertSee('data-tip-collapsed data-tip-side="right"', false)
            ->assertSee('aria-controls="host-sidebar"', false);
    });
});
