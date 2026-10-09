<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// U10: an empty page explains the next step with the mascot instead of a bare line of text.
it('guides the host on empty event pages', function (string $page, string $title, string $next): void {
    $host = User::factory()->create();
    $event = Event::factory()->for($host, 'owner')->create();

    $html = (string) actingAs($host)->get(str_replace('{event}', $event->id, $page))->assertOk()->getContent();
    $main = Str::after($html, '<main');

    // U29: Penta peeks in on an empty page.
    expect($main)->toContain('x-data="mascot({ mood: '."'peek'")
        ->and($main)->toContain($title)
        ->and($main)->toContain($next);
})->with([
    'names' => ['/host/{event}/people', 'No names yet.', 'Import CSV'],
    'games' => ['/host/{event}/games', 'No games yet.', 'Pick a question pack'],
    'results' => ['/host/{event}/results', 'No results yet.', 'Live control'],
    'deleted events' => ['/host/trash', 'No deleted events.', 'restored'],
]);
