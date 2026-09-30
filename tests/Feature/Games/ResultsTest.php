<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Enums\LoggedAction;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\GameResult;
use App\Models\Question;
use App\Models\QuestionResult;
use App\Models\User;
use App\Support\AuditLog;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Pentahoot;
use Tests\Support\TebakGambar;
use Tests\Support\TebakKata;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

uses(RefreshDatabase::class);

function freeze(Question $question, Event $event, string $name, int $rank, int $votes, int $attempt = 1): void
{
    QuestionResult::query()->create([
        'question_id' => $question->id,
        'attempt' => $attempt,
        'person_id' => Pentahoot::person($event, $name)->id,
        'rank' => $rank,
        'votes' => $votes,
        'frozen_at' => CarbonImmutable::parse('2026-10-15 03:30:00'),
    ]);
}

/**
 * Question 1 revealed with two names, question 2 revealed without votes, question 3 not played.
 */
function playedEvent(): Event
{
    $event = Pentahoot::event(['Rita Wulandari', '=cmd Budi', 'Ana Putri']);
    $game = Pentahoot::game($event, ['Most punctual?', 'Best presenter?', 'Most helpful?']);
    $first = Pentahoot::question($game, 1);
    $first->forceFill(['status' => QuestionStatus::Done])->save();
    freeze($first, $event, 'Rita Wulandari', 1, 5);
    freeze($first, $event, '=cmd Budi', 2, 3);
    Pentahoot::question($game, 2)->forceFill(['status' => QuestionStatus::Revealed])->save();

    return $event;
}

describe('results (D-3, G10)', function (): void {
    it('shows the frozen top 5 per question, and says which questions had no votes or did not run', function (): void {
        $event = playedEvent();

        actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}/results")
            ->assertOk()
            ->assertSeeInOrder(['Office Awards', 'Most punctual?', 'Rita Wulandari', '5', '=cmd Budi', '3', 'Best presenter?', 'No votes', 'Most helpful?', 'Not played'])
            // T10: stored in UTC, shown in WIB.
            ->assertSee('Last change: 15 Oct 2026, 10:30 WIB');
    });

    it('reads only the frozen table, not the raw votes (G10)', function (): void {
        $event = playedEvent();
        $first = Pentahoot::question($event->games()->firstOrFail(), 1);
        // A later reset moved the question to attempt 2; the frozen rows of attempt 1 no longer count.
        $first->pentahoot()->update(['attempt' => 2]);

        actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}/results")
            ->assertOk()
            ->assertSeeInOrder(['Most punctual?', 'Not played'])
            ->assertDontSee('Rita Wulandari');
    });

    it('exports the results as CSV that Excel cannot run as formulas', function (): void {
        $event = playedEvent();

        $csv = actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}/results.csv")
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        expect(explode("\n", trim($csv)))->toBe([
            'Game,Question,Prompt,Rank,Name,Score',
            '"Office Awards",1,"Most punctual?",1,"Rita Wulandari",5',
            '"Office Awards",1,"Most punctual?",2,"\'=cmd Budi",3',
        ]);
    });

    it('is for the owner and co-hosts only', function (): void {
        $event = playedEvent();

        actingAs(User::factory()->create())->get("/host/{$event->id}/results")->assertForbidden();
        actingAs(User::factory()->create())->get("/host/{$event->id}/results.csv")->assertForbidden();
    });
});

describe('Tebak Kata results (D-3, E10, G10)', function (): void {
    /**
     * Rita won question 1 (2 points), question 2 was surrendered, question 3 did not run, and
     * the final board was frozen with Rita first.
     */
    function playedKata(): Event
    {
        $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso']);
        $game = TebakKata::game($event, [['Capital of France?', 'PARIS', [0], 2], ['Largest ocean?', 'PACIFIC'], ['Tallest mountain?', 'EVEREST']]);
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        $game->questions()->where('position', 1)->update(['status' => QuestionStatus::Won, 'winner_person_id' => $rita->id, 'resolved_at' => now()]);
        $game->questions()->where('position', 2)->update(['status' => QuestionStatus::Surrendered, 'resolved_at' => now()]);
        GameResult::query()->create(['game_id' => $game->id, 'person_id' => $rita->id, 'rank' => 1, 'points' => 2, 'reached_at' => now(), 'frozen_at' => CarbonImmutable::parse('2026-10-15 04:00:00')]);
        $game->forceFill(['status' => GameStatus::Finished])->save();

        return $event;
    }

    it('shows the winner of each question and the frozen final board', function (): void {
        $event = playedKata();

        actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}/results")
            ->assertOk()
            ->assertSeeInOrder(['Word Guess', 'Capital of France?', 'PARIS', 'Rita Wulandari', '2 points', 'Largest ocean?', 'PACIFIC', 'No winner', 'Tallest mountain?', 'Not played', 'Final leaderboard', 'Rita Wulandari', '2'])
            ->assertSee('Last change: 15 Oct 2026, 11:00 WIB');
    });

    it('exports the winners and the final board as CSV', function (): void {
        $event = playedKata();

        $csv = actingAs($event->owner()->firstOrFail())->get("/host/{$event->id}/results.csv")->assertOk()->streamedContent();

        expect(explode("\n", trim($csv)))->toBe([
            'Game,Question,Prompt,Rank,Name,Score',
            '"Word Guess",1,"Capital of France?",,"Rita Wulandari",2',
            '"Word Guess",Final,,1,"Rita Wulandari",2',
        ]);
    });
});

describe('Tebak Gambar results (D-3, G10)', function (): void {
    it('shows and exports the winner of each question with the answer, and the final board', function (): void {
        Storage::fake('media');
        $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso']);
        $game = TebakGambar::game($event, [['Which city is this?', 'Paris', 2], ['Which ocean is this?', 'Pacific']]);
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        $game->questions()->where('position', 1)->update(['status' => QuestionStatus::Won, 'winner_person_id' => $rita->id, 'resolved_at' => now()]);
        GameResult::query()->create(['game_id' => $game->id, 'person_id' => $rita->id, 'rank' => 1, 'points' => 2, 'reached_at' => now(), 'frozen_at' => now()]);
        $game->forceFill(['status' => GameStatus::Finished])->save();
        $owner = $event->owner()->firstOrFail();

        actingAs($owner)->get("/host/{$event->id}/results")
            ->assertOk()
            ->assertSeeInOrder(['Picture Guess', 'Which city is this?', 'Paris', 'Rita Wulandari', '2 points', 'Which ocean is this?', 'Not played', 'Final leaderboard', 'Rita Wulandari']);

        $csv = actingAs($owner)->get("/host/{$event->id}/results.csv")->assertOk()->streamedContent();
        expect(explode("\n", trim($csv)))->toBe([
            'Game,Question,Prompt,Rank,Name,Score',
            '"Picture Guess",1,"Which city is this?",,"Rita Wulandari",2',
            '"Picture Guess",Final,,1,"Rita Wulandari",2',
        ]);

        app(AuditLog::class)->record(LoggedAction::WinnerPicked, $owner, $event, ['question_id' => $game->questions()->where('position', 1)->firstOrFail()->id, 'person_id' => $rita->id]);
        actingAs($owner)->get("/host/{$event->id}/logs")->assertOk()->assertSee('Which city is this?');
    });
});

describe('activity log (E13)', function (): void {
    it('lists the irreversible actions of the event, newest first, with who did them', function (): void {
        $event = playedEvent();
        $owner = $event->owner()->firstOrFail();
        $owner->forceFill(['name' => 'Arif Rahman'])->save();
        $question = Pentahoot::question($event->games()->firstOrFail(), 2);
        travelTo(CarbonImmutable::parse('2026-10-15 03:00:00'));
        app(AuditLog::class)->record(LoggedAction::JoinLocked, $owner, $event);
        travelTo(CarbonImmutable::parse('2026-10-15 03:05:00'));
        app(AuditLog::class)->record(LoggedAction::QuestionReset, $owner, $event, ['question_id' => $question->id, 'attempt' => 1]);

        actingAs($owner)->get("/host/{$event->id}/logs")
            ->assertOk()
            ->assertSeeInOrder(['10:05', 'Arif Rahman', 'Reset the votes', 'Best presenter?', '10:00', 'Arif Rahman', 'Locked new name claims']);
    });

    it('keeps other hosts out', function (): void {
        actingAs(User::factory()->create())->get('/host/'.playedEvent()->id.'/logs')->assertForbidden();
    });
});
