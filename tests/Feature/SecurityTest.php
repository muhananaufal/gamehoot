<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\Person;
use App\Support\RequiredConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\Pentahoot;

use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withServerVariables;

uses(RefreshDatabase::class);

describe('security headers (O7)', function (): void {
    it('sends a strict content security policy whose nonce the page scripts carry', function (): void {
        $response = get('/login')->assertOk();
        $policy = (string) $response->headers->get('Content-Security-Policy');

        expect($policy)
            ->toContain("default-src 'self'")
            ->toContain("frame-ancestors 'none'")
            ->toContain("object-src 'none'")
            ->toContain("base-uri 'self'")
            ->toContain("form-action 'self'")
            ->toMatch("/script-src 'self' 'nonce-[A-Za-z0-9]+'/");

        $nonce = Str::match("/'nonce-([A-Za-z0-9]+)'/", $policy);
        expect($nonce)->not->toBe('')
            ->and($response->getContent())->toContain('nonce="'.$nonce.'"');
    });

    it('allows the realtime socket and nothing else to connect', function (): void {
        config(['broadcasting.connections.reverb.client' => ['host' => 'live.example.test', 'port' => 443, 'scheme' => 'https']]);

        expect((string) get('/login')->headers->get('Content-Security-Policy'))
            ->toContain("connect-src 'self' wss://live.example.test:443");
    });

    it('sends the other protective headers on pages for hosts, phones and the projector', function (string $path): void {
        Pentahoot::event();

        get($path)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'same-origin')
            ->assertHeader('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
    })->with(['/login', '/year-end-party']);

    it('lets only this site frame the Public View, for the preview in Live control (D-10)', function (): void {
        Pentahoot::event();

        $screen = get('/year-end-party/screen')->assertOk()
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('X-Content-Type-Options', 'nosniff');

        $policy = (string) $screen->headers->get('Content-Security-Policy');
        expect($policy)->toContain("frame-ancestors 'self'");
        expect($policy)->not->toContain("frame-ancestors 'none'");
        expect((string) get('/year-end-party')->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'");
    });

    it('asks browsers to stay on HTTPS only when the request came over HTTPS', function (): void {
        expect(get('/login')->headers->has('Strict-Transport-Security'))->toBeFalse();

        get('https://localhost/login')->assertHeader('Strict-Transport-Security', 'max-age=31536000');
    });
});

describe('behind a reverse proxy', function (): void {
    beforeEach(function (): void {
        Route::get('/_test/client', fn () => ['ip' => request()->ip(), 'secure' => request()->isSecure()]);
    });

    it('trusts forwarded headers only from the configured proxies', function (): void {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        withServerVariables(['REMOTE_ADDR' => '172.18.0.4', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('/_test/client')
            ->assertExactJson(['ip' => '203.0.113.9', 'secure' => true]);
    });

    it('ignores forwarded headers from anyone else', function (): void {
        config(['trustedproxy.proxies' => '172.16.0.0/12']);

        withServerVariables(['REMOTE_ADDR' => '198.51.100.7', 'HTTP_X_FORWARDED_FOR' => '203.0.113.9', 'HTTP_X_FORWARDED_PROTO' => 'https'])
            ->get('/_test/client')
            ->assertExactJson(['ip' => '198.51.100.7', 'secure' => false]);
    });
});

describe('cookies', function (): void {
    it('marks the phone claim cookie secure when sessions are', function (): void {
        config(['session.secure' => true]);
        $event = Pentahoot::event([]);
        $newcomer = Person::factory()->for($event)->create(['name' => 'Budi Santoso']);

        $cookie = collect(post('/year-end-party/claim', ['person' => $newcomer->id])->headers->getCookies())
            ->first(fn ($cookie): bool => str_starts_with($cookie->getName(), 'pentahoot_claim'));

        expect($cookie?->isSecure())->toBeTrue();
    });
});

describe('error pages (O7)', function (): void {
    it('shows its own page for errors, in the app language', function (int $status, string $title): void {
        Route::get('/_test/http/{status}', fn (int $status) => throw new HttpException($status));

        get("/_test/http/{$status}")
            ->assertStatus($status)
            ->assertSee($title)
            ->assertSee(__('errors_page.home'))
            ->assertDontSee('Laravel');
    })->with([
        [403, 'You can’t open this page'],
        [404, 'This page doesn’t exist'],
        [419, 'This page was open too long'],
        [429, 'Too many tries at once'],
        [500, 'Something went wrong'],
        [503, 'Pentahoot is being updated'],
    ]);
});

describe('log context (K5)', function (): void {
    it('tags each request with a request id that the response returns', function (): void {
        $response = get('/login');

        expect($response->headers->get('X-Request-Id'))->toMatch('/^[0-9a-f-]{36}$/');
    });

    it('adds the request id and the event id to every log line of an event request', function (): void {
        $event = Pentahoot::event();
        Route::middleware('web')->get('/_test/{event}/log', function (Event $event) {
            Log::info('probe', ['seen' => $event->id]);

            return Context::only(['request_id', 'event_id']);
        });

        get("/_test/{$event->id}/log")->assertJsonPath('event_id', $event->id)->assertJsonStructure(['request_id']);
    });
});

describe('production settings (K6, O7)', function (): void {
    it('refuses to start production with debug output or cookies that travel over plain HTTP', function (): void {
        app()->detectEnvironment(fn (): string => 'production');
        config(['broadcasting.default' => 'log', 'app.debug' => true, 'session.secure' => false]);

        expect(RequiredConfig::missing())->toBe(['APP_DEBUG=false', 'SESSION_SECURE_COOKIE=true']);

        config(['app.debug' => false, 'session.secure' => true]);
        expect(RequiredConfig::missing())->toBe([]);
    });

    it('leaves local development alone', function (): void {
        config(['broadcasting.default' => 'log', 'app.debug' => true, 'session.secure' => false]);

        expect(RequiredConfig::missing())->toBe([]);
    });
});
