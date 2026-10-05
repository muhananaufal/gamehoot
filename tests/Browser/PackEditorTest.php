<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

it('moves a question two places with two quick arrow clicks and saves the order once (U14)', function (): void {
    $host = User::factory()->create();
    $pack = new QuestionPack(['title' => 'Office Awards', 'game_type' => GameType::Pentahoot]);
    $pack->owner()->associate($host)->save();
    actingAs($host);
    foreach (['First', 'Second', 'Third'] as $prompt) {
        post("/host/packs/{$pack->id}/questions", ['prompt' => $prompt, 'duration_seconds' => 20]);
    }

    $page = visit("/host/packs/{$pack->id}")->wait(0.5);
    // Every script returns at once (the plugin retries slow ones); the page counts the saves.
    $page->script(<<<'JS'
        (() => {
            window.__saves = 0;
            up.on('up:request:load', (event) => { if (event.request.url.endsWith('/questions/reorder')) window.__saves++; });
            const first = document.querySelector('li[data-id]').dataset.id;
            window.__first = first;
            document.querySelector(`li[data-id="${first}"] [data-move="down"]`).click();
            document.querySelector(`li[data-id="${first}"] [data-move="down"]`).click();
        })()
        JS);

    // At once, before any save: the first question is now third and the numbers follow.
    expect($page->script("[...document.querySelectorAll('li[data-id]')].findIndex((li) => li.dataset.id === window.__first)"))->toBe(2)
        ->and($page->script("[...document.querySelectorAll('li[data-id] [data-number]')].map((n) => n.textContent.trim())"))->toBe(['1', '2', '3'])
        ->and($page->script('window.__saves'))->toBe(0);

    $page->wait(2);

    expect($page->script('window.__saves'))->toBe(1)
        ->and($pack->questions()->orderBy('position')->get()->map(fn ($q) => $q->pentahoot?->prompt)->all())->toBe(['Second', 'Third', 'First']);
    $page->assertNoJavaScriptErrors();
});
