<?php

declare(strict_types=1);

use App\Enums\GameType;
use App\Enums\MediaVariant;
use App\Enums\MediaVisibility;
use App\Models\Game;
use App\Models\MediaFile;
use App\Models\PackQuestionGambar;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Pentahoot;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Storage::fake('media');
});

function gambarPack(User $owner): QuestionPack
{
    $pack = new QuestionPack(['title' => 'Famous Places', 'game_type' => GameType::TebakGambar]);
    $pack->owner()->associate($owner)->save();

    return $pack;
}

/**
 * The form as the browser sends it after resizing (E8): each image in 1920 and 720 px.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function gambarForm(array $overrides = []): array
{
    return [
        'title' => 'Which city is this?',
        'answer_text' => 'Paris',
        'points' => 1,
        'question_image' => UploadedFile::fake()->image('skyline.jpg', 1920, 1080),
        'question_image_small' => UploadedFile::fake()->image('skyline.jpg', 720, 405),
        'answer_image' => UploadedFile::fake()->image('Eiffel Tower.png', 1600, 1200),
        'answer_image_small' => UploadedFile::fake()->image('Eiffel Tower.png', 720, 540),
        ...$overrides,
    ];
}

/**
 * A real HEIC header, so the type is recognised from the content (O7), not the name.
 */
function heicFile(): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'heic');
    file_put_contents((string) $path, "\0\0\0\x18ftypheic\0\0\0\0mif1heic".str_repeat("\0", 64));

    return new UploadedFile((string) $path, 'IMG_0001.HEIC', null, null, true);
}

describe('Tebak Gambar question form (E8, F20)', function (): void {
    it('sends the form as multipart, with a picker and two sized file fields per image', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);

        actingAs($host)->get("/host/packs/{$pack->id}/questions/create")
            ->assertOk()
            ->assertSee('enctype="multipart/form-data"', false)
            ->assertSee('name="question_image"', false)
            ->assertSee('name="question_image_small"', false)
            ->assertSee('name="answer_image"', false)
            ->assertSee('name="answer_image_small"', false)
            ->assertSee('Question image')
            ->assertSee('Answer image');
    });

    it('sends forms without images the usual way, not as multipart', function (): void {
        $host = User::factory()->create();
        $pack = new QuestionPack(['title' => 'Celebrities', 'game_type' => GameType::TebakKata]);
        $pack->owner()->associate($host)->save();

        actingAs($host)->get("/host/packs/{$pack->id}/questions/create")
            ->assertOk()
            ->assertDontSee('multipart/form-data', false);
    });

    it('shows the stored images when editing, the answer image only through a signed URL (E9)', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);
        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm());
        $question = $pack->questions()->sole();
        $detail = PackQuestionGambar::query()->sole();

        actingAs($host)->get("/host/packs/{$pack->id}")->assertOk()->assertSee('Which city is this?')->assertSee('Paris');
        // The 720 px versions are enough for a preview (E14); the URLs sit in Alpine data as JSON.
        $json = fn (string $url): string => trim(json_encode($url, JSON_THROW_ON_ERROR), '"');
        $questionSmall = MediaFile::query()->findOrFail($detail->question_image_id)->variants()->sole();
        $answerSmall = MediaFile::query()->findOrFail($detail->answer_image_id)->variants()->sole();

        actingAs($host)->get("/host/packs/{$pack->id}/questions/{$question->id}/edit")
            ->assertOk()
            ->assertSee('Which city is this?')
            ->assertSee($json(url('media/'.basename($questionSmall->path))), false)
            ->assertSee($json(url("media/{$answerSmall->id}")).'?expires=', false);
    });
});

describe('Tebak Gambar pack questions (E8, E9, G11)', function (): void {
    it('stores the question image as public and the answer image as private, in both sizes', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);

        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm())
            ->assertSessionHasNoErrors()
            ->assertRedirect("/host/packs/{$pack->id}");

        $detail = PackQuestionGambar::query()->sole();
        expect($detail->only(['title', 'answer_text']))->toBe(['title' => 'Which city is this?', 'answer_text' => 'Paris']);

        $question = MediaFile::query()->findOrFail($detail->question_image_id);
        $answer = MediaFile::query()->findOrFail($detail->answer_image_id);
        expect([$question->visibility, $question->variant, $question->width])->toBe([MediaVisibility::Public, MediaVariant::W1920, 1920])
            ->and([$answer->visibility, $answer->original_name, $answer->created_by])->toBe([MediaVisibility::Private, 'Eiffel Tower.png', $host->id])
            ->and($answer->variants()->sole()->width)->toBe(720)
            ->and(MediaFile::query()->count())->toBe(4);
    });

    it('needs both images when the question is new', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);

        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm(['answer_image' => null, 'answer_image_small' => null]))
            ->assertSessionHasErrors('answer_image');
        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm(['question_image_small' => null]))
            ->assertSessionHasErrors('question_image_small');
    });

    it('refuses HEIC with a clear message, and files that are not really JPG or PNG (E8, O7)', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);

        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm(['answer_image' => heicFile()]))
            ->assertSessionHasErrors(['answer_image' => 'HEIC photos are not supported. Pick a JPG or PNG image, or change the iPhone camera setting to Most Compatible.']);

        $fakeJpg = UploadedFile::fake()->create('photo.jpg', 10, 'image/jpeg');
        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm(['question_image' => $fakeJpg]))
            ->assertSessionHasErrors(['question_image' => 'The image must be a JPG or PNG file.']);

        expect(MediaFile::query()->count())->toBe(0);
    });

    it('refuses images wider than the browser makes them, or over 2 MB (E8, K7)', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);

        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm([
            'question_image' => UploadedFile::fake()->image('wide.jpg', 2400, 1000),
            'answer_image_small' => UploadedFile::fake()->image('small.jpg', 1000, 500),
        ]))->assertSessionHasErrors(['question_image', 'answer_image_small']);

        $big = UploadedFile::fake()->image('big.jpg', 1920, 1080)->size(2049);
        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm(['answer_image' => $big]))
            ->assertSessionHasErrors('answer_image');
    });

    it('keeps the stored images when an edit leaves the fields empty, and stores new files otherwise (D-9)', function (): void {
        $host = User::factory()->create();
        $pack = gambarPack($host);
        actingAs($host)->post("/host/packs/{$pack->id}/questions", gambarForm());
        $question = $pack->questions()->sole();
        $before = PackQuestionGambar::query()->sole();

        actingAs($host)->put("/host/packs/{$pack->id}/questions/{$question->id}", ['title' => 'Which tower?', 'answer_text' => 'Eiffel', 'points' => 2])
            ->assertSessionHasNoErrors();
        $kept = PackQuestionGambar::query()->sole();
        expect([$kept->title, $kept->question_image_id, $kept->answer_image_id])->toBe(['Which tower?', $before->question_image_id, $before->answer_image_id]);

        actingAs($host)->put("/host/packs/{$pack->id}/questions/{$question->id}", gambarForm(['question_image' => UploadedFile::fake()->image('new.jpg', 800, 600), 'question_image_small' => UploadedFile::fake()->image('new.jpg', 720, 540)]))
            ->assertSessionHasNoErrors();
        $changed = PackQuestionGambar::query()->sole();
        expect($changed->question_image_id)->not->toBe($before->question_image_id);
        // The old file is kept: an older game copy may still point to it (D-9, G11).
        Storage::disk('media')->assertExists(MediaFile::query()->findOrFail($before->question_image_id)->path);
    });

    it('copies a pack question into a game with the same image rows, and edits the copy only (D-2, D-9)', function (): void {
        $event = Pentahoot::event();
        $owner = $event->owner()->firstOrFail();
        $pack = gambarPack($owner);
        actingAs($owner)->post("/host/packs/{$pack->id}/questions", gambarForm());
        $packDetail = PackQuestionGambar::query()->sole();

        actingAs($owner)->post("/host/{$event->id}/games", ['pack_id' => $pack->id])->assertSessionHasNoErrors();
        $game = Game::query()->sole();
        $copy = $game->questions()->sole();
        $copyDetail = $copy->gambar()->firstOrFail();
        expect([$copyDetail->question_image_id, $copyDetail->answer_image_id, $copyDetail->revealed_at])->toBe([$packDetail->question_image_id, $packDetail->answer_image_id, null]);
        actingAs($owner)->get("/host/{$event->id}/games/{$game->id}/questions/{$copy->id}/edit")
            ->assertOk()
            ->assertSee('Which city is this?')
            ->assertSee('enctype="multipart/form-data"', false);

        actingAs($owner)->put("/host/{$event->id}/games/{$game->id}/questions/{$copy->id}", gambarForm([
            'title' => 'Copy title',
            'answer_image' => UploadedFile::fake()->image('Louvre.jpg', 1200, 900),
            'answer_image_small' => UploadedFile::fake()->image('Louvre.jpg', 720, 540),
        ]))->assertSessionHasNoErrors();

        expect($copy->gambar()->firstOrFail()->answer_image_id)->not->toBe($packDetail->answer_image_id)
            ->and(PackQuestionGambar::query()->sole()->only(['title', 'answer_image_id']))->toBe(['title' => 'Which city is this?', 'answer_image_id' => $packDetail->answer_image_id]);
    });
});
