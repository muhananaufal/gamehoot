<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Models\PackQuestion;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

function packOf(User $owner, GameType $type = GameType::Pentahoot, string $title = 'Office Awards'): QuestionPack
{
    $pack = new QuestionPack(['title' => $title, 'game_type' => $type]);
    $pack->owner()->associate($owner)->save();

    return $pack;
}

/**
 * @return list<string>
 */
function promptsInOrder(QuestionPack $pack): array
{
    return array_values($pack->questions()->with('pentahoot')->get()
        ->map(fn (PackQuestion $question): string => (string) $question->pentahoot?->prompt)
        ->all());
}

describe('packs (D-9)', function (): void {
    it('creates a pack for a game type that has an engine', function (): void {
        $host = User::factory()->create();

        $response = actingAs($host)->post('/host/packs', ['title' => 'Celebrities', 'game_type' => 'tebak_kata']);

        $pack = QuestionPack::query()->firstOrFail();
        $response->assertRedirect("/host/packs/{$pack->id}");
        expect($pack->owner_id)->toBe($host->id)
            ->and($pack->game_type)->toBe(GameType::TebakKata);
    });

    it('refuses Tebak Gambar packs until image upload exists (F13, stage 5)', function (): void {
        actingAs(User::factory()->create())
            ->post('/host/packs', ['title' => 'Famous Places', 'game_type' => 'tebak_gambar'])
            ->assertSessionHasErrors('game_type');
    });

    it('lists only the host\'s own packs, grouped by type', function (): void {
        $host = User::factory()->create();
        packOf($host, GameType::Pentahoot, 'Office Awards');
        packOf($host, GameType::TebakKata, 'Celebrities');
        packOf(User::factory()->create(), GameType::Pentahoot, 'Someone Else Pack');

        actingAs($host)->get('/host/packs')
            ->assertOk()
            ->assertSeeInOrder(['Pentahoot', 'Office Awards', 'Word Guess', 'Celebrities'])
            ->assertDontSee('Someone Else Pack');
    });

    it('says so when the host has no packs yet', function (): void {
        actingAs(User::factory()->create())->get('/host/packs')
            ->assertOk()
            ->assertSee('No packs yet.');
    });

    it('shows the question form of each game type', function (GameType $type, string $label): void {
        $host = User::factory()->create();
        $pack = packOf($host, $type);

        actingAs($host)->get("/host/packs/{$pack->id}/questions/create")
            ->assertOk()
            ->assertSee('New question')
            ->assertSee($label);
    })->with([
        [GameType::Pentahoot, 'Time to vote'],
        [GameType::TebakKata, 'Boxes shown from the start'],
    ]);

    it('keeps other hosts out of a pack, super-admins included', function (): void {
        $pack = packOf(User::factory()->create());

        actingAs(User::factory()->create())->get("/host/packs/{$pack->id}")->assertForbidden();
        actingAs(User::factory()->superAdmin()->create())->get("/host/packs/{$pack->id}")->assertForbidden();
    });

    it('renames and soft deletes a pack', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);

        actingAs($host)->put("/host/packs/{$pack->id}", ['title' => 'Office Awards 2026'])
            ->assertRedirect("/host/packs/{$pack->id}");
        expect($pack->refresh()->title)->toBe('Office Awards 2026');

        actingAs($host)->delete("/host/packs/{$pack->id}")->assertRedirect('/host/packs');
        expect(QuestionPack::withTrashed()->findOrFail($pack->id)->trashed())->toBeTrue();
    });
});

describe('pack questions', function (): void {
    it('adds Pentahoot questions at the end of the pack', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);

        foreach (['Who works late the most?', 'Who is the best presenter?'] as $prompt) {
            actingAs($host)->post("/host/packs/{$pack->id}/questions", ['prompt' => $prompt, 'duration_seconds' => 20])
                ->assertRedirect("/host/packs/{$pack->id}");
        }

        expect(promptsInOrder($pack))->toBe(['Who works late the most?', 'Who is the best presenter?'])
            ->and($pack->questions()->pluck('position')->all())->toBe([1, 2])
            ->and($pack->questions()->pluck('points')->all())->toBe([1, 1]);
    });

    it('adds a Tebak Kata question with its points and opened boxes', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host, GameType::TebakKata);

        actingAs($host)->post("/host/packs/{$pack->id}/questions", [
            'prompt' => 'Actress and judge on a modeling show',
            'answer_text' => 'Luna Maya',
            'initial_open_indexes' => ['0'],
            'points' => '2',
        ])->assertRedirect("/host/packs/{$pack->id}");

        $question = $pack->questions()->with('kata')->firstOrFail();
        expect($question->points)->toBe(2)
            ->and($question->kata?->answer_text)->toBe('Luna Maya')
            ->and($question->kata?->initial_open_indexes)->toBe([0]);
    });

    it('validates with the engine of the pack type (F13)', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);

        actingAs($host)->post("/host/packs/{$pack->id}/questions", ['prompt' => 'Too short', 'duration_seconds' => 2])
            ->assertSessionHasErrors('duration_seconds');

        expect($pack->questions()->count())->toBe(0);
    });

    it('edits and deletes a question', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);
        actingAs($host)->post("/host/packs/{$pack->id}/questions", ['prompt' => 'Old', 'duration_seconds' => 20]);
        $question = $pack->questions()->firstOrFail();

        actingAs($host)->get("/host/packs/{$pack->id}/questions/{$question->id}/edit")->assertOk()->assertSee('Old');
        actingAs($host)->put("/host/packs/{$pack->id}/questions/{$question->id}", ['prompt' => 'New', 'duration_seconds' => 30]);
        expect(promptsInOrder($pack))->toBe(['New']);

        actingAs($host)->delete("/host/packs/{$pack->id}/questions/{$question->id}");
        expect($pack->questions()->count())->toBe(0);
    });

    it('reorders questions', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);
        foreach (['A', 'B', 'C'] as $prompt) {
            actingAs($host)->post("/host/packs/{$pack->id}/questions", ['prompt' => $prompt, 'duration_seconds' => 20]);
        }
        $ids = $pack->questions()->pluck('id')->all();

        actingAs($host)->post("/host/packs/{$pack->id}/questions/reorder", ['order' => [$ids[2], $ids[0], $ids[1]]])
            ->assertRedirect("/host/packs/{$pack->id}");

        expect(promptsInOrder($pack))->toBe(['C', 'A', 'B']);
    });

    it('refuses an order that does not list exactly the pack\'s questions', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);
        actingAs($host)->post("/host/packs/{$pack->id}/questions", ['prompt' => 'A', 'duration_seconds' => 20]);
        $foreign = packOf($host, title: 'Other');
        actingAs($host)->post("/host/packs/{$foreign->id}/questions", ['prompt' => 'X', 'duration_seconds' => 20]);

        actingAs($host)->post("/host/packs/{$pack->id}/questions/reorder", ['order' => [$foreign->questions()->value('id')]])
            ->assertSessionHasErrors('order');
    });

    it('does not reach questions through another pack', function (): void {
        $host = User::factory()->create();
        $pack = packOf($host);
        $other = packOf($host, title: 'Other');
        actingAs($host)->post("/host/packs/{$other->id}/questions", ['prompt' => 'X', 'duration_seconds' => 20]);
        $question = $other->questions()->firstOrFail();

        actingAs($host)->get("/host/packs/{$pack->id}/questions/{$question->id}/edit")->assertNotFound();
    });
});
