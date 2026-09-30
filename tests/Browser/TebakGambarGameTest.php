<?php

declare(strict_types=1);

use App\Actions\Games\RunQuestionAction;
use App\Enums\GameType;
use App\Games\GameEngines;
use App\Models\Event;
use App\Models\MediaFile;
use App\Models\Person;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Pentahoot;
use Tests\Support\TebakGambar;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

/*
| The images are real files on the media disk, served like in production: public ones through the
| public/media link (php artisan storage:link), private ones through signed URLs. Each test removes
| the files it made. Without a Reverb server the screens follow the game by polling /state (T2).
*/

afterEach(function (): void {
    foreach (MediaFile::query()->get(['path']) as $file) {
        Storage::disk('media')->delete($file->path);
    }
});

/**
 * @param  array<string, mixed>  $input
 */
function runGambar(Event $event, Question $question, string $action, array $input = []): void
{
    app(RunQuestionAction::class)->handle(
        app(GameEngines::class)->live($question->game()->firstOrFail()->type),
        $action,
        $event->refresh(),
        $question,
        $event->owner()->firstOrFail(),
        $input,
    );
}

it('shows the question image on the projector and phones, and the answer image after Reveal (E9, E14)', function (): void {
    $event = Pentahoot::event(['Budi Santoso'], ['show_on_devices' => true]);
    Person::factory()->for($event)->create(['name' => 'Rita Wulandari']);
    $question = TebakGambar::running($event, [['Which city is this?', 'Paris']])->questions()->sole();

    $screen = visit('/year-end-party/screen')->resize(1920, 1080)->assertSee('The next question is coming');
    $phone = visit('/year-end-party')->on()->iPhone15()->click('Rita Wulandari')->assertSee('Answer out loud to the host');

    runGambar($event, $question, 'show');
    // The projector loads the 1920 px file, phones the 720 px one (E14).
    $screen->assertSee('Which city is this?')
        ->assertScript('document.querySelector("[data-test=question-image]")?.naturalWidth === 1920')
        ->assertMissing('@answer-image');
    $phone->assertScript('document.querySelector("[data-test=question-image]")?.naturalWidth === 720')
        ->assertMissing('@answer-image');

    runGambar($event, $question, 'reveal');
    $screen->assertScript('document.querySelector("[data-test=answer-image]")?.naturalWidth === 1920');
    $phone->assertScript('document.querySelector("[data-test=answer-image]")?.naturalWidth === 720');

    runGambar($event, $question, 'winner', ['person' => Pentahoot::person($event, 'Budi Santoso')->id]);
    $screen->assertSee('Winner:')->assertSee('Budi Santoso')->assertNoJavaScriptErrors();
    $phone->assertSee('Budi Santoso')->assertNoJavaScriptErrors();
});

it('runs a Tebak Gambar question from Live control: show, Reveal and winner (D-5, D-6, E7, E9)', function (): void {
    $event = Pentahoot::event(['Rita Wulandari', 'Budi Santoso']);
    TebakGambar::running($event, [['Which city is this?', 'Paris'], ['Which ocean is this?', 'Pacific']]);

    actingAs($event->owner()->firstOrFail());

    $page = visit("/host/{$event->id}")
        ->assertSee('Questions (2)')
        ->click('Which city is this?')
        ->assertSee('Question 1 of 2')
        ->assertSee('Paris');

    $page->click('Reveal answer')
        ->click('@confirm-reveal')
        ->assertSee('Answer on screen')
        // A dialog left open would make the whole page inert.
        ->assertScript('[...document.querySelectorAll("dialog")].every((dialog) => !dialog.open)')
        // E7: no Skip once the answer is revealed.
        ->assertScript('[...document.querySelectorAll("button")].find((button) => button.textContent.trim() === "Skip")?.disabled === true')
        ->type('winner-search', 'rit')
        ->click('Rita Wulandari')
        ->click('Pick winner')
        ->click('@confirm-winner')
        ->assertSee('Winner:')
        ->assertNoJavaScriptErrors();
});

it('makes a picked image 1920 and 720 px wide in the browser before upload (E8, E14)', function (): void {
    $host = User::factory()->create();
    $pack = new QuestionPack(['title' => 'Famous Places', 'game_type' => GameType::TebakGambar]);
    $pack->owner()->associate($host)->save();

    actingAs($host);

    $page = visit("/host/packs/{$pack->id}/questions/create")->assertSee('Question image');

    // Picks a 3000 x 1500 PNG, as a host would from the file dialog.
    $page->script(<<<'JS'
        () => {
            const canvas = document.createElement('canvas');
            canvas.width = 3000;
            canvas.height = 1500;
            canvas.getContext('2d').fillRect(0, 0, 3000, 1500);
            canvas.toBlob((blob) => {
                const files = new DataTransfer();
                files.items.add(new File([blob], 'Big Photo.png', { type: 'image/png' }));
                const picker = document.getElementById('field-question_image');
                picker.files = files.files;
                picker.dispatchEvent(new Event('change', { bubbles: true }));
            }, 'image/png');
        }
        JS);

    $page->assertScript('document.querySelector("input[name=question_image_small]").files.length === 1')
        ->assertVisible('@question_image-preview');

    $page->script(<<<'JS'
        () => Promise.all(['question_image', 'question_image_small'].map((name) => createImageBitmap(document.querySelector(`input[name=${name}]`).files[0])))
            .then((images) => { window.pickedSizes = images.map((image) => `${image.width}x${image.height}`).join(','); })
        JS);

    $page->assertScript('window.pickedSizes === "1920x960,720x360"')
        ->assertScript('document.querySelector("input[name=question_image]").files[0].name === "Big Photo.png"')
        ->assertNoJavaScriptErrors();
});
