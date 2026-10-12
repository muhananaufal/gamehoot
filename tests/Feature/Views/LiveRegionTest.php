<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// U12: pages whose data other people change follow the host channel and refresh one region.
// F25: only Names shows who has joined, so only its region also follows the claims.
it('refreshes the data region of the Names, Games and Activity log pages', function (string $page, string $region, string $component): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->open()->create();

    $html = (string) actingAs($host)->get("/host/{$event->id}/{$page}")->assertOk()->getContent();

    expect($html)->toContain('id="realtime-config"')
        ->and($html)->toContain("event.{$event->id}.host")
        ->and($html)->toContain("id=\"{$region}\" x-data=\"{$component}\"");
})->with([
    'names' => ['people', 'names-live', 'liveRegion({ claims: true })'],
    'games' => ['games', 'games-live', 'liveRegion'],
    'activity log' => ['logs', 'log-live', 'liveRegion'],
]);
