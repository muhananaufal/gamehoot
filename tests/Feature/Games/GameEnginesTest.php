<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Exceptions\UnsupportedGameType;
use App\Games\GameEngines;
use App\Games\PentahootEngine;
use App\Games\QuestionCopier;
use App\Games\TebakKataEngine;
use App\Models\Game;
use App\Models\PackQuestion;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;

uses(RefreshDatabase::class);

/**
 * @param  array<string, mixed>  $input
 * @return array<string, list<string>>
 */
function engineErrors(GameType $type, array $input): array
{
    /** @var array<string, list<string>> */
    return Validator::make($input, app(GameEngines::class)->for($type)->questionRules(creating: true))->errors()->toArray();
}

function packQuestionFor(GameType $type): PackQuestion
{
    $pack = new QuestionPack(['title' => 'Pack', 'game_type' => $type]);
    $pack->owner()->associate(User::factory()->create())->save();

    $question = new PackQuestion(['position' => 1, 'points' => 2]);
    $question->pack()->associate($pack)->save();

    return $question;
}

describe('F13 engine registry', function (): void {
    it('resolves one engine per supported game type', function (): void {
        $engines = app(GameEngines::class);

        expect($engines->for(GameType::Pentahoot))->toBeInstanceOf(PentahootEngine::class)
            ->and($engines->for(GameType::TebakKata))->toBeInstanceOf(TebakKataEngine::class)
            ->and($engines->supportedTypes())->toBe([GameType::Pentahoot, GameType::TebakKata]);
    });

    it('refuses a game type without an engine yet', function (): void {
        app(GameEngines::class)->for(GameType::TebakGambar);
    })->throws(UnsupportedGameType::class);
});

describe('Pentahoot question form', function (): void {
    it('accepts a prompt with a duration between 5 and 300 seconds (T9)', function (int $seconds): void {
        expect(engineErrors(GameType::Pentahoot, ['prompt' => 'Best dressed', 'duration_seconds' => $seconds]))->toBe([]);
    })->with([5, 300]);

    it('rejects a duration outside 5 to 300 seconds (T9)', function (int $seconds): void {
        expect(engineErrors(GameType::Pentahoot, ['prompt' => 'Best dressed', 'duration_seconds' => $seconds]))
            ->toHaveKey('duration_seconds');
    })->with([4, 301]);
});

describe('Tebak Kata question form', function (): void {
    it('accepts an answer with some boxes opened from the start', function (): void {
        expect(engineErrors(GameType::TebakKata, [
            'prompt' => 'Capital of France',
            'answer_text' => 'Paris',
            'initial_open_indexes' => [0, 4],
            'points' => 1,
        ]))->toBe([]);
    });

    it('accepts opened boxes sent as form strings and stores them as sorted integers', function (): void {
        $input = [
            'prompt' => 'Capital of France',
            'answer_text' => ' Paris ',
            'initial_open_indexes' => ['4', '0'],
            'points' => '1',
        ];
        $engine = app(GameEngines::class)->for(GameType::TebakKata);

        expect(engineErrors(GameType::TebakKata, $input))->toBe([])
            ->and($engine->detailAttributes($input))->toBe([
                'prompt' => 'Capital of France',
                'answer_text' => 'Paris',
                'initial_open_indexes' => [0, 4],
            ]);
    });

    it('treats a form without opened boxes as all boxes hidden', function (): void {
        $input = ['prompt' => 'Capital of Italy', 'answer_text' => 'Rome', 'points' => '1'];

        expect(engineErrors(GameType::TebakKata, $input))->toBe([])
            ->and(app(GameEngines::class)->for(GameType::TebakKata)->detailAttributes($input)['initial_open_indexes'])->toBe([]);
    });

    it('rejects an answer longer than 40 characters (T9)', function (): void {
        expect(engineErrors(GameType::TebakKata, [
            'prompt' => 'Long',
            'answer_text' => str_repeat('a', 41),
            'initial_open_indexes' => [],
            'points' => 1,
        ]))->toHaveKey('answer_text');
    });

    it('rejects an answer that cannot be shown as boxes (E5)', function (): void {
        expect(engineErrors(GameType::TebakKata, [
            'prompt' => 'Shout',
            'answer_text' => 'hello!',
            'initial_open_indexes' => [],
            'points' => 1,
        ]))->toHaveKey('answer_text');
    });

    it('rejects opened boxes that do not exist in the answer', function (): void {
        expect(engineErrors(GameType::TebakKata, [
            'prompt' => 'Capital of France',
            'answer_text' => 'Paris',
            'initial_open_indexes' => [5],
            'points' => 1,
        ]))->toHaveKey('initial_open_indexes');
    });

    it('rejects opening every box from the start', function (): void {
        expect(engineErrors(GameType::TebakKata, [
            'prompt' => 'Capital of Italy',
            'answer_text' => 'Rome',
            'initial_open_indexes' => [0, 1, 2, 3],
            'points' => 1,
        ]))->toHaveKey('initial_open_indexes');
    });

    it('accepts only 0, 1 or 2 points (E16)', function (): void {
        expect(engineErrors(GameType::TebakKata, [
            'prompt' => 'Bonus',
            'answer_text' => 'Rome',
            'initial_open_indexes' => [],
            'points' => 3,
        ]))->toHaveKey('points');
    });
});

describe('D-9 copying a pack question into a game', function (): void {
    it('copies a Pentahoot question ready to start', function (): void {
        $engine = app(GameEngines::class)->for(GameType::Pentahoot);
        $source = packQuestionFor(GameType::Pentahoot);
        $engine->savePackDetail($source, $engine->detailAttributes(['prompt' => 'Best dressed', 'duration_seconds' => 30]));
        $game = Game::factory()->ofType(GameType::Pentahoot)->create();

        $copy = app(QuestionCopier::class)->copy($source, $game, 3);

        $detail = $copy->pentahoot()->firstOrFail();
        expect($copy->status)->toBe(QuestionStatus::Ready)
            ->and($copy->position)->toBe(3)
            ->and($copy->source_pack_question_id)->toBe($source->id)
            ->and($detail->prompt)->toBe('Best dressed')
            ->and($detail->duration_seconds)->toBe(30)
            ->and($detail->attempt)->toBe(1)
            ->and($detail->ends_at)->toBeNull();
    });

    it('copies a Tebak Kata question queued, with its points and opened boxes', function (): void {
        $engine = app(GameEngines::class)->for(GameType::TebakKata);
        $source = packQuestionFor(GameType::TebakKata);
        $engine->savePackDetail($source, $engine->detailAttributes([
            'prompt' => 'Capital of France',
            'answer_text' => 'Paris',
            'initial_open_indexes' => [0, 4],
            'points' => 2,
        ]));
        $game = Game::factory()->ofType(GameType::TebakKata)->create();

        $copy = app(QuestionCopier::class)->copy($source, $game, 1);

        $detail = $copy->kata()->firstOrFail();
        expect($copy->status)->toBe(QuestionStatus::Queued)
            ->and($copy->points)->toBe(2)
            ->and($detail->answer_text)->toBe('Paris')
            ->and($detail->initial_open_indexes)->toBe([0, 4])
            ->and($detail->opened_indexes)->toBe([0, 4]);
    });

    it('does not change the copy when the pack question is edited later', function (): void {
        $engine = app(GameEngines::class)->for(GameType::Pentahoot);
        $source = packQuestionFor(GameType::Pentahoot);
        $engine->savePackDetail($source, ['prompt' => 'Old prompt', 'duration_seconds' => 30]);
        $copy = app(QuestionCopier::class)->copy($source, Game::factory()->create(), 1);

        $engine->savePackDetail($source, ['prompt' => 'New prompt', 'duration_seconds' => 60]);

        expect($copy->pentahoot()->firstOrFail()->prompt)->toBe('Old prompt')
            ->and($source->pentahoot()->firstOrFail()->prompt)->toBe('New prompt');
    });
});
