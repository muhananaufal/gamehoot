<?php

declare(strict_types=1);

use App\Actions\Accounts\ResetPassword;
use App\Models\Event;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

it('lists events the host owns or co-hosts, and nothing else (C-1, C-2)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Own Party']);
    $cohosted = Event::factory()->create(['name' => 'Finance Quiz']);
    $cohosted->cohosts()->attach($host);
    Event::factory()->create(['name' => 'Someone Else']);

    actingAs($host)->get('/host')
        ->assertOk()
        ->assertSee('Own Party')
        ->assertSee('Finance Quiz')
        ->assertSee('Co-host')
        ->assertDontSee('Someone Else');
});

it('does not show other hosts\' events to a super-admin either (C-4)', function (): void {
    Event::factory()->create(['name' => 'Division Event']);

    actingAs(User::factory()->superAdmin()->create())->get('/host')
        ->assertOk()
        ->assertDontSee('Division Event')
        ->assertSee('No events yet.');
});

it('filters, searches and sorts events in the browser, with the counts per status (U4)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Draft One']);
    Event::factory()->for($host, 'owner')->open()->create(['name' => 'Open One']);

    $html = (string) actingAs($host)->get('/host')
        ->assertSee('All · 2')
        ->assertSee('Open · 1')
        ->getContent();

    expect($html)->toContain('x-data="tableView(')
        ->and($html)->toMatch('/<tr data-row data-search="open one[^"]*" data-filter="open"/')
        ->and($html)->toMatch('/<tr data-row data-search="draft one[^"]*" data-filter="draft"/')
        ->and($html)->toContain('aria-label="Search events"');
});

it('says when each event was made and last changed, and who owns a co-hosted one (U6)', function (): void {
    $host = User::factory()->create();
    $owner = User::factory()->create(['name' => 'Dina (HR)']);
    travelTo(CarbonImmutable::parse('2026-10-01 03:00:00'));
    $cohosted = Event::factory()->for($owner, 'owner')->create(['name' => 'Gathering']);
    $cohosted->cohosts()->attach($host);
    travelTo(CarbonImmutable::parse('2026-10-02 04:30:00'));
    $cohosted->touch();

    actingAs($host)->get('/host')
        ->assertSee('Created 1 Oct 2026')
        ->assertSee('2 Oct, 11:30')
        ->assertSee('Co-host · owner Dina (HR)');
});

it('ends an open session once a super-admin resets the password (C-5)', function (): void {
    $host = User::factory()->create();
    actingAs($host)->get('/host')->assertOk();

    app(ResetPassword::class)->handle($host, 'temporary-pass-7', User::factory()->superAdmin()->create());

    get('/host')->assertRedirect('/login');
    assertGuest();
});
