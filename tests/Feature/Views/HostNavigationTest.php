<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

// U18: the host panel navigates without reloading; the phone and the Public View do not.
it('marks the host panel for Unpoly and gives it the page nonce', function (): void {
    $host = User::factory()->create();

    $response = actingAs($host)->get('/host')->assertOk();
    $html = (string) $response->getContent();
    $nonce = Str::match("/'nonce-([^']+)'/", (string) $response->headers->get('Content-Security-Policy'));

    expect($nonce)->not->toBe('')
        ->and($html)->toContain('<meta name="csp-nonce" content="'.$nonce.'">')
        ->and($html)->toContain('data-host-shell')
        ->and($html)->toContain('id="host-content" up-main')
        ->and($html)->toContain('id="host-nav" up-hungry')
        // Signing out leaves the panel: a plain request.
        ->and($html)->toMatch('/<form method="POST" action="[^"]*\/logout" up-submit="false">/')
        // Session messages arrive with each new page, so the toasts are part of it.
        ->and(Str::after($html, 'id="host-content"'))->toContain('aria-live="polite"');
});

it('keeps plain page loads on the phone and the Public View', function (): void {
    $event = Event::factory()->open()->create(['slug' => 'gathering']);

    expect((string) get('/gathering')->getContent())->not->toContain('data-host-shell')
        ->and((string) get('/gathering/screen')->getContent())->not->toContain('data-host-shell');
});
