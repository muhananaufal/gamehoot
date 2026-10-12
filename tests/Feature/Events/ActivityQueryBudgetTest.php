<?php

declare(strict_types=1);

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\Person;
use App\Models\User;
use App\Results\EventActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\Pentahoot;

uses(RefreshDatabase::class);

/**
 * Audit PERF-5: the Activity log names the question, game, name or account of each entry with
 * one query per kind, not up to three per entry; a long event stays one page load.
 */
it('names the subjects of the activity log with the same queries for 2 or 20 entries (E13)', function (): void {
    $countFor = function (int $rounds): array {
        $event = Event::factory()->for(User::factory(), 'owner')->create();
        $host = $event->owner()->firstOrFail();
        $person = Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);

        for ($round = 1; $round <= $rounds; $round++) {
            $game = Pentahoot::game($event, ["Question {$round}?"], "Game {$round}");
            $question = Pentahoot::question($game);
            $account = User::factory()->create(['name' => "Co-host {$round}"]);

            foreach ([
                [LoggedAction::GameStarted, ['game_id' => $game->id]],
                [LoggedAction::QuestionRevealed, ['question_id' => $question->id]],
                [LoggedAction::ClaimReleased, ['person_id' => $person->id]],
                [LoggedAction::CohostAdded, ['account_id' => $account->id]],
            ] as [$action, $payload]) {
                ActionLog::query()->create(['event_id' => $event->id, 'user_id' => $host->id, 'action' => $action, 'payload' => $payload]);
            }
        }

        $queries = 0;
        DB::listen(function () use (&$queries): void {
            $queries++;
        });
        $entries = EventActivity::for($event);

        return [$queries, array_column($entries, 'subject')];
    };

    [$few, $subjects] = $countFor(2);
    expect($subjects)->toBe(['Co-host 2', 'Rita Wulandari', 'Question 2?', 'Game 2', 'Co-host 1', 'Rita Wulandari', 'Question 1?', 'Game 1']);

    [$many] = $countFor(5);
    expect($many)->toBe($few);
});
