<?php

declare(strict_types=1);

use App\Enums\EventStatus;
use App\Models\Event;
use App\Models\Person;
use App\People\ClaimCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withCookie;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $attributes
 */
function openEvent(array $attributes = []): Event
{
    return Event::factory()->open()->create(['name' => 'Gathering 2026', 'slug' => 'gathering-2026', ...$attributes]);
}

/**
 * @param  TestResponse<Response>  $response
 */
function claimCookieFrom(TestResponse $response, Event $event): string
{
    // Decrypted value, the same the phone sends back (withCookie encrypts it again).
    $cookie = $response->getCookie(ClaimCookie::name($event));
    expect($cookie)->not->toBeNull();

    return (string) $cookie?->getValue();
}

describe('general join page (B-1, F12)', function (): void {
    it('lists the names nobody has claimed yet', function (): void {
        $event = openEvent();
        Person::factory()->for($event)->create(['name' => 'Budi Santoso']);
        Person::factory()->for($event)->create(['name' => 'Bunga Lestari', 'claimed_at' => now(), 'claim_token_hash' => hash('sha256', 'x')]);

        get('/gathering-2026')
            ->assertOk()
            ->assertSee('Pick your name to join the game')
            ->assertSee('Budi Santoso')
            ->assertDontSee('Bunga Lestari');
    });

    it('says the event cannot be found for unknown and deleted events', function (): void {
        get('/no-such-event')->assertNotFound()->assertSee('EVENT_NOT_FOUND');

        $event = openEvent();
        $event->delete();
        get('/gathering-2026')->assertNotFound();
    });

    it('says the event has not started while it is a draft', function (): void {
        Event::factory()->create(['slug' => 'later-event']);

        get('/later-event')->assertOk()->assertSee('not open yet');
    });

    it('says the event has ended once it is closed (D-3)', function (): void {
        openEvent(['status' => EventStatus::Finished]);

        get('/gathering-2026')->assertOk()->assertSee('This event has ended');
    });

    it('leaves app routes alone (F10)', function (): void {
        get('/login')->assertOk()->assertSee('Host sign in');
    });
});

describe('claiming a name (B-1, G3)', function (): void {
    it('claims a name for this phone and stores only the hash of the token', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);

        $response = post('/gathering-2026/claim', ['person' => $budi->id])->assertRedirect('/gathering-2026/play');

        $token = claimCookieFrom($response, $event);
        $budi->refresh();
        expect($budi->claimed_at)->not->toBeNull()
            ->and($budi->claim_token_hash)->toBe(hash('sha256', $token))
            ->and($budi->claim_token_hash)->not->toBe($token);

        withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026/play')
            ->assertOk()
            ->assertSee('You’re in, Budi Santoso!', false);
    });

    it('refuses a name that another phone already claimed (NAME_TAKEN)', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso', 'claimed_at' => now(), 'claim_token_hash' => hash('sha256', 'first-phone')]);

        post('/gathering-2026/claim', ['person' => $budi->id])
            ->assertStatus(409)
            ->assertSee('This name is already in use')
            ->assertSee('NAME_TAKEN');

        expect($budi->refresh()->claim_token_hash)->toBe(hash('sha256', 'first-phone'));
    });

    it('refuses a name from another event', function (): void {
        openEvent();
        $stranger = Person::factory()->create();

        post('/gathering-2026/claim', ['person' => $stranger->id])->assertNotFound();
        expect($stranger->refresh()->claimed_at)->toBeNull();
    });

    it('sends a phone that already joined straight back to its game', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create();
        $token = claimCookieFrom(post('/gathering-2026/claim', ['person' => $budi->id]), $event);

        withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026')->assertRedirect('/gathering-2026/play');
    });

    it('sends a phone without a valid claim back to the name list', function (): void {
        openEvent();

        get('/gathering-2026/play')->assertRedirect('/gathering-2026');
        withCookie('pentahoot_claim_x', 'forged')->get('/gathering-2026/play')->assertRedirect('/gathering-2026');
    });

    it('drops the phone back to the name list after the host releases the name (B-6)', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create();
        $token = claimCookieFrom(post('/gathering-2026/claim', ['person' => $budi->id]), $event);

        $budi->refresh()->forceFill(['claim_token_hash' => null, 'claimed_at' => null])->save();

        withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026/play')->assertRedirect('/gathering-2026');
    });

    it('refuses claims while the event is not open', function (): void {
        $event = Event::factory()->create(['slug' => 'draft-event']);
        $budi = Person::factory()->for($event)->create();

        post('/draft-event/claim', ['person' => $budi->id])->assertOk()->assertSee('not open yet');
        expect($budi->refresh()->claimed_at)->toBeNull();
    });
});

describe('join lock (B-7)', function (): void {
    it('refuses new claims but lets a phone that already joined reconnect', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create();
        $rita = Person::factory()->for($event)->create();
        $token = claimCookieFrom(post('/gathering-2026/claim', ['person' => $budi->id]), $event);
        $event->forceFill(['join_locked_at' => now()])->save();

        get('/gathering-2026')->assertStatus(423)->assertSee('Joining is closed for now')->assertSee('CLAIMS_LOCKED');
        post('/gathering-2026/claim', ['person' => $rita->id])->assertStatus(423);
        get("/gathering-2026/j/{$rita->join_token}")->assertStatus(423);
        expect($rita->refresh()->claimed_at)->toBeNull();

        withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026/play')->assertOk();
    });
});

describe('personal link (B-1, T5)', function (): void {
    it('claims the owner\'s name straight away', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);

        $response = get("/gathering-2026/j/{$budi->join_token}")->assertRedirect('/gathering-2026/play');

        expect($budi->refresh()->claim_token_hash)->toBe(hash('sha256', claimCookieFrom($response, $event)));
    });

    it('lets the same phone open its link again', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create();
        $token = claimCookieFrom(get("/gathering-2026/j/{$budi->join_token}"), $event);

        withCookie(ClaimCookie::name($event), $token)->get("/gathering-2026/j/{$budi->join_token}")
            ->assertRedirect('/gathering-2026/play');
    });

    it('refuses the link on another phone once the name is claimed', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create();
        get("/gathering-2026/j/{$budi->join_token}");

        get("/gathering-2026/j/{$budi->join_token}")->assertStatus(409)->assertSee('NAME_TAKEN');
    });

    it('stops working once the host makes a new link', function (): void {
        $event = openEvent();
        $budi = Person::factory()->for($event)->create(['join_token' => 'oldtokenoldtoken']);
        $budi->forceFill(['join_token' => 'newtokennewtoken'])->save();

        get('/gathering-2026/j/oldtokenoldtoken')->assertNotFound();
    });
});
