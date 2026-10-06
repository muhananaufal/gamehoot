<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

    it('scrolls on its own, apart from the page, and keeps theme and sign out in view', function (): void {
        $host = User::factory()->create();

        $html = (string) actingAs($host)->get('/host')->getContent();

        expect($html)->toMatch('/<aside id="host-sidebar"[^>]*lg:sticky[^>]*lg:h-dvh/')
            ->and($html)->toMatch('/<nav [^>]*class="[^"]*overflow-y-auto[^"]*" aria-label="Main"/');
    });

    it('can be narrowed to icons on wide screens, with a labelled button', function (): void {
        $host = User::factory()->create();

        actingAs($host)->get('/host')
            ->assertSee('aria-label="Collapse sidebar"', false)
            ->assertSee('aria-controls="host-sidebar"', false);
    });
});
