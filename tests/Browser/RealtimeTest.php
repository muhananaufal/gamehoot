<?php

declare(strict_types=1);

use App\Actions\Events\CloseEvent;
use App\Actions\People\ClaimName;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| Browser tests run without a Reverb server (BROADCAST_CONNECTION=null), so every screen
| here keeps itself current by polling /state (T2). The socket path is covered by the
| feature tests of the broadcasts and by the manual check with Reverb (README).
*/

it('updates the Public View lobby count on a 16:9 projector as names are claimed (F23, T2)', function (): void {
    $event = Event::factory()->open()->create(['name' => 'Year-End Party', 'slug' => 'year-end-party']);
    $rita = Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);

    $page = visit('/year-end-party/screen')
        ->resize(1920, 1080)
        ->assertSee('Join on your phone at')
        ->assertSee('/year-end-party')
        ->assertSee('0 players joined')
        ->assertSee('Connection lost. The screen still updates every few seconds.');

    app(ClaimName::class)->handle($event, $rita);

    $page->assertSee('1 player joined')->assertNoJavaScriptErrors();
});

it('moves a waiting phone to the ended screen when the host closes the event (F1, T2)', function (): void {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->open()->create(['name' => 'Year-End Party', 'slug' => 'year-end-party']);
    Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);

    $page = visit('/year-end-party')
        ->on()->iPhone15()
        ->click('Rita Wulandari')
        ->assertSee('You’re in, Rita Wulandari!');

    app(CloseEvent::class)->handle($event, $owner);

    $page->assertSee('This event has ended')
        ->assertDontSee('You’re in, Rita Wulandari!')
        ->assertNoJavaScriptErrors();
});

it('shows hosts the live join count on Live control (F2, F20)', function (): void {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->open()->create(['name' => 'Year-End Party', 'slug' => 'year-end-party']);
    $rita = Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);
    Person::factory()->for($event)->create(['name' => 'Budi Santoso']);

    actingAs($owner);

    $page = visit("/host/{$event->id}")
        ->assertSee('Live control')
        ->assertSee('of 2 names')
        ->assertSee('Open')
        ->assertSeeIn('@joined-count', '0');

    app(ClaimName::class)->handle($event, $rita);

    $page->assertSeeIn('@joined-count', '1')->assertNoJavaScriptErrors();
});
