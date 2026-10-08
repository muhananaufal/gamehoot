<?php

declare(strict_types=1);

use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\People\ClaimCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Pentahoot;
use Tests\Support\TebakGambar;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\withCookie;

uses(RefreshDatabase::class);

function screensEvent(): Event
{
    return Event::factory()->open()->create(['name' => 'Gathering 2026', 'slug' => 'gathering-2026', 'state_version' => 9]);
}

/**
 * The settings the page hands to resources/js/app.js.
 *
 * @param  TestResponse<Response>  $response
 * @return array<string, mixed>
 */
function realtimeConfigOf(TestResponse $response): array
{
    preg_match('#<script type="application/json" id="realtime-config">(.*?)</script>#s', (string) $response->getContent(), $match);
    expect($match)->toHaveKey(1);

    $config = json_decode($match[1] ?? '', true, flags: JSON_THROW_ON_ERROR);
    expect($config)->toBeArray();

    /** @var array<string, mixed> $config */
    return $config;
}

describe('Public View (F23)', function (): void {
    it('shows the lobby with the join link, in the event screen theme', function (): void {
        $event = screensEvent();
        Person::factory()->for($event)->claimed()->create();

        $response = get('/gathering-2026/screen')
            ->assertOk()
            ->assertSee('data-theme="dark"', false)
            ->assertSee('Gathering 2026')
            // E18: the QR code with the join link, drawn from tokens.
            ->assertSee('data-test="join-qr"', false)
            ->assertSee('<svg fill="currentColor" aria-hidden="true"', false)
            ->assertSee((string) preg_replace('#^https?://#', '', url('/gathering-2026')));

        $config = realtimeConfigOf($response);
        expect($config)->not->toHaveKey('initial.host');
        expect($config)
            ->toHaveKey('stateUrl', url('/gathering-2026/state'))
            ->toHaveKey('channels', [['name' => "event.{$event->id}.public", 'private' => false]])
            ->toHaveKey('initial.version', 9)
            ->toHaveKey('initial.lobby.joined', 1);
    });

    it('follows the device motion setting only, never a choice made in the panel on the same browser (U27, U31)', function (): void {
        screensEvent();

        get('/gathering-2026/screen')
            ->assertOk()
            ->assertSee('data-motion-device-only', false)
            ->assertSee('document.documentElement.dataset.motion', false)
            ->assertDontSee('pentahoot-motion', false);
    });

    it('answers unknown events with the not found screen', function (): void {
        get('/no-such-event/screen')->assertNotFound()->assertSee('EVENT_NOT_FOUND');
    });
});

describe('player screen', function (): void {
    it('follows the public channel of the event', function (): void {
        $event = screensEvent();
        $person = Person::factory()->for($event)->create();
        $token = ClaimCookie::newToken();
        $person->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

        $response = withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026/play')->assertOk();

        expect(realtimeConfigOf($response))->toHaveKey('channels', [['name' => "event.{$event->id}.public", 'private' => false]]);
    });

    it('puts the phone\'s own name and vote into the first snapshot, so a reload never flashes "Time is up" (E4)', function (): void {
        $event = screensEvent();
        $person = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);
        $token = ClaimCookie::newToken();
        $person->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

        $initial = realtimeConfigOf(withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026/play')->assertOk())['initial'];

        expect($initial)->toBeArray()->toHaveKey('me.name', 'Budi Santoso');
    });

    it('tells the phone where to pick a name again once its name is released or deleted, and shows the name from the store (B-6)', function (): void {
        $event = screensEvent();
        $person = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);
        $token = ClaimCookie::newToken();
        $person->forceFill(['claim_token_hash' => ClaimCookie::hash($token), 'claimed_at' => now()])->save();

        $response = withCookie(ClaimCookie::name($event), $token)->get('/gathering-2026/play')->assertOk();

        expect(realtimeConfigOf($response))->toHaveKey('joinUrl', url('/gathering-2026'))
            ->and((string) $response->getContent())->toContain('x-text="$store.realtime.me?.name ??');
        expect(realtimeConfigOf(get('/gathering-2026/screen')))->toHaveKey('joinUrl', null);
    });
});

describe('Live control (F2, C-2)', function (): void {
    it('gives hosts the live panel on both channels', function (): void {
        $event = screensEvent();

        $response = actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}")
            ->assertOk()
            ->assertSee('Live control')
            ->assertSee(url('/gathering-2026/screen'));

        expect(realtimeConfigOf($response))
            ->toHaveKey('stateUrl', url("/host/{$event->id}/state"))
            ->toHaveKey('channels', [
                ['name' => "event.{$event->id}.public", 'private' => false],
                ['name' => "event.{$event->id}.host", 'private' => true],
            ])
            ->toHaveKey('initial.host.names', 0);
    });

    it('refuses other hosts', function (): void {
        $event = screensEvent();

        actingAs(User::factory()->create())->get("/host/{$event->id}")->assertForbidden();
    });
});

describe('socket settings (P1)', function (): void {
    it('hands browsers the public key and address, never the secret', function (): void {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'public-key-123',
            'broadcasting.connections.reverb.secret' => 'secret-value-456',
            'broadcasting.connections.reverb.client' => ['host' => 'play.example.test', 'port' => 443, 'scheme' => 'https'],
        ]);
        screensEvent();

        $response = get('/gathering-2026/screen')->assertDontSee('secret-value-456');

        expect(realtimeConfigOf($response))->toHaveKey('socket', [
            'key' => 'public-key-123',
            'host' => 'play.example.test',
            'port' => 443,
            'scheme' => 'https',
        ]);
    });

    it('leaves the socket out when not broadcasting through Reverb, so pages poll (T2)', function (): void {
        screensEvent();

        expect(realtimeConfigOf(get('/gathering-2026/screen')))->toHaveKey('socket', null);
    });
});

describe('Tebak Kata screens (stage 4)', function (): void {
    it('gives hosts Live control for a Tebak Kata game with the name list for the winner', function (): void {
        $event = Pentahoot::event(['Rita Wulandari']);
        TebakKata::running($event, [['Capital of France?', 'PARIS', [0]]]);

        actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}")
            ->assertOk()
            ->assertSee('Capital of France?')
            ->assertSee('Finish game')
            ->assertSee('Rita Wulandari');
    });

    it('never puts the answer in the page of the Public View or the phone (E5, F2)', function (): void {
        $event = Pentahoot::event(['Rita Wulandari']);
        $game = TebakKata::running($event, [['Capital of France?', 'PARIS', [0]]]);
        $question = $game->questions()->sole();
        $question->forceFill(['status' => QuestionStatus::Shown])->save();
        $game->forceFill(['current_question_id' => $question->id])->save();
        $event->bumpStateVersion();
        $event->save();

        get('/year-end-party/screen')->assertOk()->assertDontSee('PARIS');
    });
});

describe('Tebak Gambar screens (stage 5)', function (): void {
    it('gives hosts Live control for a Tebak Gambar game with Reveal and the name list', function (): void {
        Storage::fake('media');
        $event = Pentahoot::event(['Rita Wulandari']);
        TebakGambar::running($event, [['Which city is this?', 'Paris']]);

        actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}")
            ->assertOk()
            ->assertSee('Which city is this?')
            ->assertSee('Reveal answer')
            ->assertSee('Finish game')
            ->assertSee('Rita Wulandari');
    });

    it('never puts the answer or its image in the page of the Public View before Reveal (E9, F2)', function (): void {
        Storage::fake('media');
        $event = Pentahoot::event(['Rita Wulandari']);
        $game = TebakGambar::running($event, [['Which city is this?', 'Paris']]);
        $question = $game->questions()->sole();
        $question->forceFill(['status' => QuestionStatus::Shown])->save();
        $game->forceFill(['current_question_id' => $question->id])->save();
        $event->bumpStateVersion();
        $event->save();
        $answerImage = $question->gambar()->firstOrFail()->answer_image_id;

        get('/year-end-party/screen')->assertOk()->assertDontSee('Paris')->assertDontSee($answerImage);
    });
});

describe('Live control after the last question (D-8)', function (): void {
    it('tells the host every question is done and offers to finish the game right there', function (string $kind): void {
        $event = Pentahoot::event();
        $kind === 'pentahoot' ? Pentahoot::running($event) : TebakKata::running($event);

        $html = (string) actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}")->assertOk()->getContent();
        $flag = $kind === 'pentahoot' ? 'allDone' : 'allResolved';

        // The notice shows only once nothing is left, and holds its own Finish game form.
        expect($html)->toMatch('/x-show="'.$flag.'"[^>]*>[\s\S]*?All questions are done[\s\S]*?\/games\/[0-9a-f-]+\/finish/');
    })->with(['pentahoot', 'tebak']);
});
