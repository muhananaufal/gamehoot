<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// D-9, B-6: version counters start at 0; a game copied before the marker existed has none (W14).
it('adds the counters as additive columns', function (): void {
    $column = function (string $table, string $name): array {
        $found = collect(Schema::getColumns($table))->firstWhere('name', $name);
        expect($found)->toBeArray();

        return is_array($found) ? $found : [];
    };

    expect($column('question_packs', 'content_version'))->toMatchArray(['nullable' => false, 'default' => '0'])
        ->and($column('events', 'roster_version'))->toMatchArray(['nullable' => false, 'default' => '0'])
        ->and($column('games', 'pack_version'))->toMatchArray(['nullable' => true, 'default' => null]);
});

// D-9: when the copied questions of a game were last edited in the event; empty until then (W14).
it('adds games.edited_at as an empty-by-default column', function (): void {
    $found = collect(Schema::getColumns('games'))->firstWhere('name', 'edited_at');

    expect($found)->toBeArray()->toMatchArray(['nullable' => true, 'default' => null]);
});
