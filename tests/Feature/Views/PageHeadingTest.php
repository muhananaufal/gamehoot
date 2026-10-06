<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

// U2: every host page names itself with one h1 at the head of the content, with or without page actions.
it('gives every host page one heading', function (): void {
    $admin = User::factory()->create(['is_super_admin' => true]);
    $event = Event::factory()->for($admin, 'owner')->open()->create(['name' => 'Gathering 2026']);
    $game = Pentahoot::game($event, ['Most punctual?'], 'Office Awards');
    actingAs($admin);

    $pages = [
        '/host' => 'Events',
        '/host/packs' => 'Question packs',
        '/host/trash' => 'Deleted events',
        "/host/{$event->id}" => 'Live control',
        "/host/{$event->id}/games" => 'Games',
        "/host/{$event->id}/games/{$game->id}/questions" => 'Office Awards',
        "/host/{$event->id}/people" => 'Names',
        "/host/{$event->id}/results" => 'Results',
        "/host/{$event->id}/logs" => 'Activity log',
        "/host/{$event->id}/settings" => 'Settings',
        '/admin/users' => 'Host accounts',
        '/admin/trash' => 'Deleted events',
    ];

    foreach ($pages as $url => $title) {
        $html = (string) Pest\Laravel\get($url)->assertOk()->getContent();

        expect(substr_count($html, '<h1'))->toBe(1, "{$url} has one h1")
            ->and($html)->toMatch('#<h1[^>]*>\s*'.preg_quote($title, '#').'\s*</h1>#');
    }
});
