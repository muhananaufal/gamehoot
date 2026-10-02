<?php

declare(strict_types=1);

use App\Actions\Accounts\ResetPassword;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\get;

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

it('filters events by status', function (): void {
    $host = User::factory()->create();
    Event::factory()->for($host, 'owner')->create(['name' => 'Draft One']);
    Event::factory()->for($host, 'owner')->open()->create(['name' => 'Open One']);

    $html = (string) actingAs($host)->get('/host?status=open')
        ->assertSee('All · 2')
        ->assertSee('Open · 1')
        ->getContent();

    // The filter applies to the list; the sidebar still lists recent events of any status (U1).
    $main = Str::after($html, '<main');
    expect($main)->toContain('Open One')
        ->and($main)->not->toContain('Draft One');
});

it('ends an open session once a super-admin resets the password (C-5)', function (): void {
    $host = User::factory()->create();
    actingAs($host)->get('/host')->assertOk();

    app(ResetPassword::class)->handle($host, 'temporary-pass-7', User::factory()->superAdmin()->create());

    get('/host')->assertRedirect('/login');
    assertGuest();
});
