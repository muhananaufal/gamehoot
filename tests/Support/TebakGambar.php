<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\GameType;
use App\Enums\MediaVisibility;
use App\Enums\QuestionStatus;
use App\Media\MediaStore;
use App\Models\Event;
use App\Models\Game;
use App\Models\MediaFile;
use App\Models\Question;
use App\Models\User;
use Illuminate\Http\UploadedFile;

/**
 * Builds Tebak Gambar games for tests. Questions are [title, answer, points]; every question
 * gets a public question image and a private answer image, in both sizes, on the media disk.
 */
final class TebakGambar
{
    /**
     * @param  list<array{0: string, 1: string, 2?: int}>  $questions
     */
    public static function game(Event $event, array $questions = [['Which city is this?', 'Paris'], ['Which ocean is this?', 'Pacific']], string $title = 'Picture Guess'): Game
    {
        $owner = $event->owner()->firstOrFail();
        $last = $event->games()->max('position');
        $game = Game::factory()->for($event)->create([
            'type' => GameType::TebakGambar,
            'title' => $title,
            'position' => is_numeric($last) ? (int) $last + 1 : 1,
        ]);

        foreach ($questions as $index => $question) {
            $copy = Question::factory()->for($game)->create([
                'position' => $index + 1,
                'points' => $question[2] ?? 1,
                'status' => QuestionStatus::Queued,
            ]);
            $copy->gambar()->forceCreate([
                'title' => $question[0],
                'answer_text' => $question[1],
                'question_image_id' => self::image($owner, MediaVisibility::Public, 'question.jpg')->id,
                'answer_image_id' => self::image($owner, MediaVisibility::Private, "{$question[1]}.jpg")->id,
            ]);
        }

        return $game;
    }

    /**
     * @param  list<array{0: string, 1: string, 2?: int}>  $questions
     */
    public static function running(Event $event, array $questions = [['Which city is this?', 'Paris'], ['Which ocean is this?', 'Pacific']]): Game
    {
        $game = self::game($event, $questions);
        $event->forceFill(['active_game_id' => $game->id, 'names_locked_at' => now()])->save();

        return $game;
    }

    public static function image(User $owner, MediaVisibility $visibility, string $name = 'image.jpg'): MediaFile
    {
        return app(MediaStore::class)->store(
            UploadedFile::fake()->image($name, 1920, 1080),
            UploadedFile::fake()->image($name, 720, 405),
            $visibility,
            $owner,
        );
    }
}
