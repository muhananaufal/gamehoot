<?php

declare(strict_types=1);

use App\Enums\QuestionStatus;
use App\Games\Replay\Moment;
use App\Games\Replay\ReplayMoments;
use App\Models\Event;
use App\Models\Person;
use App\Models\QuestionResult;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Pentahoot;

uses(RefreshDatabase::class);

/**
 * Audit PERF-5: Live control lists the moments that can be replayed (D-11) on every load; a
 * Pentahoot game reads its frozen winners in one query, not a few per revealed category.
 */
it('lists the replay moments of 2 or 8 revealed categories with the same queries (D-11, G10)', function (): void {
    $countFor = function (int $categories): array {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        $game = Pentahoot::game($event, array_map(fn (int $number): string => "Category {$number}?", range(1, $categories)));
        $winner = Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);

        foreach ($game->questions()->get() as $question) {
            $question->forceFill(['status' => QuestionStatus::Revealed])->save();
            QuestionResult::query()->create(['question_id' => $question->id, 'attempt' => 1, 'person_id' => $winner->id, 'rank' => 1, 'votes' => 3, 'frozen_at' => now()]);
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $groups = app(ReplayMoments::class)->forEvent($event);

        return [$queries, array_map(fn (Moment $moment): array => $moment->names, $groups[0]['moments'] ?? [])];
    };

    [$few, $winners] = $countFor(2);
    expect($winners)->toBe([['Rita Wulandari'], ['Rita Wulandari']]);

    [$many] = $countFor(8);
    expect($many)->toBe($few);
});
