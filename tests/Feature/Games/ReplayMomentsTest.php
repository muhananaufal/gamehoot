<?php

declare(strict_types=1);

use App\Enums\GameStatus;
use App\Enums\QuestionStatus;
use App\Enums\ReplayKind;
use App\Games\Replay\Moment;
use App\Games\Replay\ReplayMoments;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Question;
use App\Models\QuestionResult;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\Pentahoot;
use Tests\Support\TebakKata;

uses(RefreshDatabase::class);

/**
 * A Pentahoot game whose first category was revealed (Rita 3 votes, Budi 1) and whose second
 * is still open, so only the first can come back.
 */
function replayablePentahoot(Event $event): Game
{
    $game = Pentahoot::game($event, ['Most punctual?', 'Best presenter?']);
    $first = Pentahoot::question($game);
    $first->forceFill(['status' => QuestionStatus::Revealed])->save();

    foreach ([['Rita Wulandari', 1, 3], ['Budi Santoso', 2, 1]] as [$name, $rank, $votes]) {
        QuestionResult::query()->create([
            'question_id' => $first->id,
            'attempt' => 1,
            'person_id' => Pentahoot::person($event, $name)->id,
            'rank' => $rank,
            'votes' => $votes,
            'frozen_at' => now(),
        ]);
    }

    Pentahoot::question($game, 2)->forceFill(['status' => QuestionStatus::Live])->save();

    return $game;
}

/**
 * A finished Tebak Kata game: Rita won the first question, nobody the second (surrendered).
 */
function replayableKata(Event $event): Game
{
    $game = TebakKata::game($event, [['Capital of France?', 'PARIS'], ['Largest ocean?', 'PACIFIC']], 'Word Round');
    $rita = Pentahoot::person($event, 'Rita Wulandari');
    [$first, $second] = $game->questions()->orderBy('position')->get()->all();
    $first->forceFill(['status' => QuestionStatus::Won, 'winner_person_id' => $rita->id, 'resolved_at' => CarbonImmutable::parse('2026-10-07 10:00:00')])->save();
    $second->forceFill(['status' => QuestionStatus::Surrendered, 'resolved_at' => CarbonImmutable::parse('2026-10-07 10:01:00')])->save();
    $game->forceFill(['status' => GameStatus::Finished])->save();
    GameResult::query()->create([
        'game_id' => $game->id,
        'person_id' => $rita->id,
        'rank' => 1,
        'points' => 1,
        'reached_at' => CarbonImmutable::parse('2026-10-07 10:00:00'),
        'frozen_at' => now(),
    ]);

    return $game;
}

/**
 * @return list<string> one line per moment: kind, question number, title, names
 */
function replayMomentLines(Event $event): array
{
    $lines = [];

    foreach (app(ReplayMoments::class)->forEvent($event) as $group) {
        foreach ($group['moments'] as $moment) {
            $lines[] = implode(' | ', [$group['game']->title, $moment->kind->value, $moment->number ?? '-', $moment->title, implode(', ', $moment->names)]);
        }
    }

    return $lines;
}

function markReplay(Event $event, ReplayKind $kind, Game $game, ?Question $question = null): void
{
    $event->forceFill(['replay_kind' => $kind, 'replay_game_id' => $game->id, 'replay_question_id' => $question?->id])->save();
}

describe('the moments a host can replay (D-11)', function (): void {
    it('lists only what was on screen: revealed categories, won and surrendered questions, leaderboards after a win and final podiums', function (): void {
        $event = Pentahoot::event();
        replayablePentahoot($event);
        replayableKata($event);

        expect(replayMomentLines($event))->toBe([
            'Office Awards | result | 1 | Most punctual? | Rita Wulandari',
            'Word Round | final | - | Word Round | Rita Wulandari',
            'Word Round | result | 1 | PARIS | Rita Wulandari',
            'Word Round | leaderboard | 1 | PARIS | Rita Wulandari',
            'Word Round | result | 2 | PACIFIC | ',
        ]);
    });

    it('leaves out games with nothing to replay yet', function (): void {
        $event = Pentahoot::event();
        Pentahoot::game($event);

        expect(app(ReplayMoments::class)->forEvent($event))->toBe([]);
    });

    it('finds a moment only when it can be replayed', function (): void {
        $event = Pentahoot::event();
        $pentahoot = replayablePentahoot($event);
        $kata = replayableKata($event);
        $moments = app(ReplayMoments::class);
        $surrendered = $kata->questions()->where('position', 2)->firstOrFail();

        expect($moments->find($event, ReplayKind::Result, $pentahoot, Pentahoot::question($pentahoot)))->toBeInstanceOf(Moment::class)
            ->and($moments->find($event, ReplayKind::Result, $pentahoot, Pentahoot::question($pentahoot, 2)))->toBeNull()
            ->and($moments->find($event, ReplayKind::Leaderboard, $kata, $surrendered))->toBeNull()
            ->and($moments->find($event, ReplayKind::Final, $pentahoot))->toBeNull()
            ->and($moments->find($event, ReplayKind::Final, $kata))->toBeInstanceOf(Moment::class);
    });
});

describe('what the Public View draws for a replay (D-11)', function (): void {
    it('draws nothing while no moment is replayed', function (): void {
        expect(app(ReplayMoments::class)->payload(Pentahoot::event()))->toBeNull();
    });

    it('draws a Pentahoot category as its ranking', function (): void {
        $event = Pentahoot::event();
        $game = replayablePentahoot($event);
        markReplay($event, ReplayKind::Result, $game, Pentahoot::question($game));

        expect(app(ReplayMoments::class)->payload($event->fresh() ?? $event))->toBe([
            'kind' => 'result',
            'type' => 'pentahoot',
            'game' => 'Office Awards',
            'number' => 1,
            'title' => 'Most punctual?',
            'rows' => [
                ['rank' => 1, 'name' => 'Rita Wulandari', 'score' => 3],
                ['rank' => 2, 'name' => 'Budi Santoso', 'score' => 1],
            ],
        ]);
    });

    it('draws a Tebak question with its answer and winner, a surrendered one without', function (): void {
        $event = Pentahoot::event();
        $game = replayableKata($event);
        [$won, $surrendered] = $game->questions()->orderBy('position')->get()->all();

        markReplay($event, ReplayKind::Result, $game, $won);
        $winner = app(ReplayMoments::class)->payload($event->fresh() ?? $event);
        markReplay($event, ReplayKind::Result, $game, $surrendered);
        $nobody = app(ReplayMoments::class)->payload($event->fresh() ?? $event);

        expect($winner)->toBe(['kind' => 'result', 'type' => 'tebak_kata', 'game' => 'Word Round', 'number' => 1, 'title' => 'PARIS', 'prompt' => 'Capital of France?', 'rows' => [['rank' => 1, 'name' => 'Rita Wulandari', 'score' => 1]]])
            ->and($nobody)->toBe(['kind' => 'result', 'type' => 'tebak_kata', 'game' => 'Word Round', 'number' => 2, 'title' => 'PACIFIC', 'prompt' => 'Largest ocean?', 'rows' => []]);
    });

    it('draws the leaderboard as it stood after a question, and the final podium of a game', function (): void {
        $event = Pentahoot::event();
        $game = replayableKata($event);
        $won = $game->questions()->where('position', 1)->firstOrFail();

        markReplay($event, ReplayKind::Leaderboard, $game, $won);
        $board = app(ReplayMoments::class)->payload($event->fresh() ?? $event);
        markReplay($event, ReplayKind::Final, $game);
        $final = app(ReplayMoments::class)->payload($event->fresh() ?? $event);

        expect($board)->toBe(['kind' => 'leaderboard', 'type' => 'tebak_kata', 'game' => 'Word Round', 'number' => 1, 'title' => 'PARIS', 'rows' => [['rank' => 1, 'name' => 'Rita Wulandari', 'score' => 1]]])
            ->and($final)->toBe(['kind' => 'final', 'type' => 'tebak_kata', 'game' => 'Word Round', 'number' => null, 'title' => 'Word Round', 'rows' => [['rank' => 1, 'name' => 'Rita Wulandari', 'score' => 1]]]);
    });

    it('draws nothing for a marker that no longer points at a moment', function (): void {
        $event = Pentahoot::event();
        $game = replayablePentahoot($event);
        markReplay($event, ReplayKind::Result, $game, Pentahoot::question($game, 2));

        expect(app(ReplayMoments::class)->payload($event->fresh() ?? $event))->toBeNull();
    });
});
