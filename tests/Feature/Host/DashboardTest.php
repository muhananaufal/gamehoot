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
        ->assertSee('All statuses')
        ->getContent();
    // U22: the status dropdown carries a count per status (the page escapes its quotes).
    $options = str_replace(chr(92).'u0022', '"', $html);

    expect($options)->toContain('"label":"All statuses","count":2')
        ->toContain('"label":"Open","count":1')
        ->and($html)->toContain('x-data="tableView({ key: \'events\', sort: \'created\', dir: \'desc\' })"')
        ->and($html)->toContain('Rows per page')
        ->and($html)->toMatch('/data-sort-created="\d+" data-date="\d{4}-\d{2}-\d{2}"/')
        ->and($html)->toMatch('/<tr data-row data-search="open one[^"]*" data-filter="open"/')
        ->and($html)->toMatch('/<tr data-row data-search="draft one[^"]*" data-filter="draft"/')
        ->and($html)->toContain('placeholder="Search name, link or owner"');
});

it('says when each event was made and last changed, and who owns a co-hosted one (U6)', function (): void {
    $host = User::factory()->create();
    $owner = User::factory()->create(['name' => 'Dina (HR)']);
    travelTo(CarbonImmutable::parse('2026-10-01 03:00:00'));
    $cohosted = Event::factory()->for($owner, 'owner')->create(['name' => 'Gathering']);
    $cohosted->cohosts()->attach($host);
    travelTo(CarbonImmutable::parse('2026-10-02 04:30:00'));
    $cohosted->touch();

    // Created is a sortable column; when it last changed sits under the event link (U6).
    actingAs($host)->get('/host')
        ->assertSeeInOrder(['Gathering', 'Co-host', 'Changed 2 Oct, 11:30', 'Owned by Dina (HR)', '1 Oct 2026'])
        ->assertSee('aria-sort="ariaSort(\'created\')"', false)
        ->assertDontSee('Created 1 Oct 2026');
});

it('ends an open session once a super-admin resets the password (C-5)', function (): void {
    $host = User::factory()->create();
    actingAs($host)->get('/host')->assertOk();

    app(ResetPassword::class)->handle($host, 'temporary-pass-7', User::factory()->superAdmin()->create());

    get('/host')->assertRedirect('/login');
    assertGuest();
});

it('filters events by a created date range from a themed calendar (U15)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['created_at' => '2026-10-01 18:30:00']);
    // 18:30 UTC on 1 Oct is 2 Oct in WIB (T10).
    travelTo(CarbonImmutable::parse('2026-10-02 20:00:00'));

    $html = (string) actingAs($host)->get('/host')->assertOk()->getContent();

    // 20:00 UTC on 2 Oct is already 3 Oct in WIB, so "today" in the picker is 3 Oct.
    expect($html)->toContain("x-data=\"dateRangePicker({ today: '2026-10-03' })\"")
        ->and($html)->toContain('data-date="2026-10-02"')
        ->and($html)->toContain('role="grid"')
        ->and($html)->toContain('aria-label="Filter by created date"');
});

it('marks the role of the host on each event with a pill next to its name (U21)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Mine']);

    actingAs($host)->get('/host')->assertSeeInOrder(['Mine', 'Owner', '/'])->assertDontSee('Owned by');
});

it('sorts the events by every column (U4)', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create();

    $html = (string) actingAs($host)->get('/host')->getContent();

    foreach (['name', 'status', 'names', 'games', 'created'] as $key) {
        expect($html)->toContain("sortBy('{$key}')")->toContain("data-sort-{$key}=");
    }
});
