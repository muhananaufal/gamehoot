<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// U12: pages whose data other people change follow the host channel and refresh one region.
it('refreshes the data region of the Names, Games and Activity log pages', function (string $page, string $region): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->open()->create();

    $html = (string) actingAs($host)->get("/host/{$event->id}/{$page}")->assertOk()->getContent();

    expect($html)->toContain('id="realtime-config"')
        ->and($html)->toContain("event.{$event->id}.host")
        ->and($html)->toContain("id=\"{$region}\" x-data=\"liveRegion\"");
})->with([
    'names' => ['people', 'names-live'],
    'games' => ['games', 'games-live'],
    'activity log' => ['logs', 'log-live'],
]);
