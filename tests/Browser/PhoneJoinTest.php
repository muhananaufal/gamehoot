<?php

declare(strict_types=1);

use App\Actions\People\ReleaseClaim;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('lets a player on an iPhone search, claim a name and get it back after a release (B-1, B-6, F9)', function (): void {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->open()->create(['name' => 'Year-End Party', 'slug' => 'year-end-party']);
    $rita = Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);
    Person::factory()->for($event)->create(['name' => 'Budi Santoso']);

    $page = visit('/year-end-party')
        ->on()->iPhone15()
        ->assertSee('Pick your name to join the game')
        ->type('name-search', 'rita')
        ->assertDontSee('Budi Santoso')
        ->type('name-search', 'zzz')
        ->assertSee('No name matches your search.')
        ->type('name-search', 'rita')
        ->click('Rita Wulandari')
        ->assertSee('You’re in, Rita Wulandari!')
        ->assertNoJavaScriptErrors();

    // Reconnecting keeps the claim.
    $page->navigate('/year-end-party')->assertSee('You’re in, Rita Wulandari!');

    app(ReleaseClaim::class)->handle($event, $rita, $owner);

    $page->navigate('/year-end-party/play')
        ->assertSee('Pick your name to join the game')
        ->assertSee('Rita Wulandari')
        ->assertNoJavaScriptErrors();
});

it('shows the locked screen on a phone when claims are locked (B-7)', function (): void {
    $event = Event::factory()->open()->create(['slug' => 'locked-party', 'join_locked_at' => now()]);
    Person::factory()->for($event)->create();

    visit('/locked-party')
        ->on()->iPhone15()
        ->assertSee('Joining is closed for now')
        ->assertSee('CLAIMS_LOCKED')
        ->assertNoJavaScriptErrors();
});
