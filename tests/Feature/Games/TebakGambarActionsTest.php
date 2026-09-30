<?php

declare(strict_types=1);

use App\Enums\Audience;
use App\Enums\GameStatus;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\GameResult;
use App\Models\Question;
use App\Realtime\EventSnapshot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\Support\Pentahoot;
use Tests\Support\TebakGambar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('media');
});

/**
 * @param  array<string, mixed>  $input
 * @return TestResponse<Response>
 */
function gambarAction(Event $event, Question $question, string $action, array $input = []): TestResponse
{
    return actingAs($event->owner()->firstOrFail())->postJson("/host/{$event->id}/questions/{$question->id}/{$action}", $input);
}

function gambarQuestion(Game $game, int $position): Question
{
    return $game->questions()->where('position', $position)->firstOrFail();
}

/**
 * @return array<string, mixed>
 */
function gambarSnapshot(Event $event, Audience $audience = Audience::Public): array
{
    return app(EventSnapshot::class)->for($event->refresh(), $audience);
}

/**
 * @param  array<string, mixed>  $snapshot
 */
function gambarUrl(array $snapshot, string $key): string
{
    $url = data_get($snapshot, $key);
    expect($url)->toBeString();

    return is_string($url) ? $url : '';
}

describe('show and Reveal (E7, E9, F8)', function (): void {
    it('shows the question image to everyone, and the answer only to hosts', function (): void {
        $event = Pentahoot::event();
        $game = TebakGambar::running($event, [['Which city is this?', 'Paris']]);
        $question = gambarQuestion($game, 1);
        $detail = $question->gambar()->with(['questionImage.variants', 'answerImage.variants'])->firstOrFail();

        gambarAction($event, $question, 'show')->assertNoContent();

        $public = gambarSnapshot($event);
        expect($public)->toHaveKey('game.question.title', 'Which city is this?')
            ->toHaveKey('game.question.revealed', false)
            ->toHaveKey('game.question.answer_image', null);
        expect(data_get($public, 'game.question.image.large'))->toBe(url('media/'.basename((string) $detail->questionImage?->path)));
        expect(data_get($public, 'game.question.image.small'))->toEndWith('-w720.jpg');

        // E9: neither the answer, nor the answer image, nor its id travels on the public channel.
        $json = json_encode($public, JSON_THROW_ON_ERROR);
        expect($json)->not->toContain('Paris');
        expect($json)->not->toContain((string) $detail->answer_image_id);

        $host = gambarSnapshot($event, Audience::Host);
        expect($host)->toHaveKey('game.question.answer', 'Paris');
        get(gambarUrl($host, 'game.question.answer_image.large'))->assertOk();
    });

    it('reveals the answer image once, with a signed URL for the projector and one for phones', function (): void {
        $event = Pentahoot::event();
        $game = TebakGambar::running($event);
        $question = gambarQuestion($game, 1);
        gambarAction($event, $question, 'show');

        gambarAction($event, $question, 'reveal')->assertNoContent();

        expect($question->gambar()->value('revealed_at'))->not->toBeNull();
        $public = gambarSnapshot($event);
        expect($public)->toHaveKey('game.question.revealed', true)
            ->toHaveKey('game.question.status', 'shown');
        get(gambarUrl($public, 'game.question.answer_image.large'))->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        get(gambarUrl($public, 'game.question.answer_image.small'))->assertOk();
        // The answer text stays with hosts (spec 09: answer_text is for hosts only).
        expect(json_encode($public, JSON_THROW_ON_ERROR))->not->toContain('Paris');

        gambarAction($event, $question, 'reveal')->assertConflict()->assertJson(['code' => 'STALE_ACTION']);
    });

    it('turns Skip off once the answer is revealed (E7)', function (): void {
        $event = Pentahoot::event();
        $game = TebakGambar::running($event);
        $question = gambarQuestion($game, 1);
        gambarAction($event, $question, 'show');
        gambarAction($event, $question, 'reveal');

        gambarAction($event, $question, 'skip')->assertConflict();

        expect($question->refresh()->status)->toBe(QuestionStatus::Shown);
    });

    it('still skips before Reveal, once, to the end of the queue (E6)', function (): void {
        $event = Pentahoot::event();
        $game = TebakGambar::running($event);
        $question = gambarQuestion($game, 1);
        gambarAction($event, $question, 'show');

        gambarAction($event, $question, 'skip')->assertNoContent();

        expect($question->refresh()->only(['status', 'skip_used']))->toBe(['status' => QuestionStatus::Queued, 'skip_used' => true]);
    });
});

describe('winner, surrender and results (D-6, E10, E12, E13)', function (): void {
    it('picks a winner after Reveal and shows the answer image with the winner', function (): void {
        $event = Pentahoot::event();
        $game = TebakGambar::running($event, [['Which city is this?', 'Paris', 2]]);
        $question = gambarQuestion($game, 1);
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        gambarAction($event, $question, 'show');
        gambarAction($event, $question, 'reveal');

        gambarAction($event, $question, 'winner', ['person' => $rita->id])->assertNoContent();

        expect(gambarSnapshot($event))->toHaveKey('game.question.winner', 'Rita Wulandari')
            ->toHaveKey('game.question.status', 'won');
        gambarAction($event, $question, 'leaderboard')->assertNoContent();
        expect(gambarSnapshot($event))->toHaveKey('game.leaderboard.0.name', 'Rita Wulandari')
            ->toHaveKey('game.leaderboard.0.points', 2);
    });

    it('shows the answer image when the question ends without Reveal (E13)', function (): void {
        $event = Pentahoot::event();
        $question = gambarQuestion(TebakGambar::running($event), 1);
        gambarAction($event, $question, 'show');

        gambarAction($event, $question, 'surrender')->assertNoContent();

        $public = gambarSnapshot($event);
        expect($public)->toHaveKey('game.question.revealed', true);
        get(gambarUrl($public, 'game.question.answer_image.large'))->assertOk();
    });

    it('freezes the final top 5 when the game finishes (E12, G10)', function (): void {
        $event = Pentahoot::event();
        $game = TebakGambar::running($event, [['A?', 'A'], ['B?', 'B']]);
        $rita = Pentahoot::person($event, 'Rita Wulandari');
        $budi = Pentahoot::person($event, 'Budi Santoso');
        foreach ([[1, $rita], [2, $budi]] as [$position, $person]) {
            $question = gambarQuestion($game, $position);
            gambarAction($event, $question, 'show');
            gambarAction($event, $question, 'winner', ['person' => $person->id]);
        }

        actingAs($event->owner()->firstOrFail())->post("/host/{$event->id}/games/{$game->id}/finish")->assertRedirect();

        expect($game->refresh()->status)->toBe(GameStatus::Finished)
            // E10: same points, Rita got there first.
            ->and(GameResult::query()->where('game_id', $game->id)->orderBy('rank')->pluck('person_id')->all())->toBe([$rita->id, $budi->id]);
        expect(gambarSnapshot($event))->toHaveKey('game.final.0.name', 'Rita Wulandari');
    });
});

it('fits the Tebak Gambar snapshot in the broadcast budget (F14)', function (): void {
    $event = Pentahoot::event();
    $game = TebakGambar::running($event, array_map(fn (int $n): array => ["Question {$n}", "Answer {$n}"], range(1, 30)));
    $question = gambarQuestion($game, 1);
    gambarAction($event, $question, 'show');
    gambarAction($event, $question, 'reveal');

    foreach ([Audience::Public, Audience::Host] as $audience) {
        expect(strlen(json_encode(gambarSnapshot($event, $audience), JSON_THROW_ON_ERROR)))->toBeLessThan(5000);
    }
});
