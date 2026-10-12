<?php

declare(strict_types=1);

use App\Models\Event;
use App\Models\User;
use App\Results\EventResults;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Pentahoot;

uses(RefreshDatabase::class);

/**
 * Audit PERF-5: the Results page and its export read the frozen rows of a Pentahoot game in one
 * query, not one per question (G10).
 */
it('reads the results of a game with 2 or 8 questions with the same queries (D-3, G10)', function (): void {
    $countFor = function (int $questions): int {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        Pentahoot::game($event, array_map(fn (int $number): string => "Question {$number}?", range(1, $questions)));

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $results = EventResults::for($event);
        expect($results->games[0]['questions'])->toHaveCount($questions);

        return $queries;
    };

    expect($countFor(8))->toBe($countFor(2));
});
