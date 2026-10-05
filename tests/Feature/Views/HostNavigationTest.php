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

it('puts the page title and its actions at the top of the content, not in the top bar (U2)', function (): void {
    $html = (string) actingAs(User::factory()->create())->get('/host')->assertOk()->getContent();

    expect(Str::between($html, '<header', '</header>'))->not->toContain('New event')
        ->and(Str::after($html, '<main'))->toMatch('/<h1[^>]*>\s*Events\s*<\/h1>/')
        ->and(Str::after($html, '<main'))->toContain('New event');
});

it('opens New event and Add host as modals that close on their result page (U13)', function (): void {
    $admin = User::factory()->superAdmin()->create();

    expect((string) actingAs($admin)->get('/host')->getContent())
        ->toMatch('/<a href="[^"]*\/host\/events\/create"[^>]*up-layer="new modal"[^>]*up-accept-location="\/host\/\*\/people"/')
        ->and((string) actingAs($admin)->get('/admin/users')->getContent())
        ->toMatch('/<a href="[^"]*\/admin\/users\/create"[^>]*up-layer="new modal"[^>]*up-accept-location="\/admin\/users"/');

    // The modal leaves the address alone, so a refresh stays on the list behind it.
    expect((string) actingAs($admin)->get('/host')->getContent())->toMatch('/up-layer="new modal"[^>]*up-history="false"/')
        ->and((string) actingAs($admin)->get('/admin/users')->getContent())->toMatch('/up-layer="new modal"[^>]*up-history="false"/');

    // Cancel closes the modal; up-dismiss must stay empty (its value is read as JSON).
    $createPage = (string) actingAs($admin)->get('/host/events/create')->getContent();
    expect($createPage)->toContain('up-dismiss=""')
        ->and($createPage)->not->toContain('up-dismiss="up-dismiss"');

    // The form pages mark what a modal shows: the form, not the page around it.
    expect((string) actingAs($admin)->get('/host/events/create')->getContent())->toMatch('/up-main="modal"[^>]*>[\s\S]*<form method="POST"/')
        ->and((string) actingAs($admin)->get('/admin/users/create')->getContent())->toMatch('/up-main="modal"[^>]*>[\s\S]*<form method="POST"/');
});

it('keeps the shared confirmation dialog and row menu inside the panel, so their forms move without reloading (U5, U18)', function (): void {
    $html = (string) actingAs(User::factory()->create())->get('/host')->getContent();

    $dom = new DOMDocument;
    libxml_use_internal_errors(true);
    $dom->loadHTML($html);
    libxml_clear_errors();
    $xpath = new DOMXPath($dom);

    $count = function (string $query) use ($xpath): int {
        $nodes = $xpath->query($query);

        return $nodes === false ? 0 : $nodes->length;
    };

    expect($count('//*[@data-host-shell]//dialog[@aria-labelledby="confirm-title"]//form'))->toBe(1)
        ->and($count('//*[@data-host-shell]//*[@role="menu"]'))->toBeGreaterThan(0)
        // A save that swaps only part of a page (the pack editor, U14) still brings its message.
        ->and($count('//*[@id="toasts"][@up-hungry]'))->toBe(1);
});
