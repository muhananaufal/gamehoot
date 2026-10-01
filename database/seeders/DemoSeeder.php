<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Events\CreateEvent;
use App\Actions\Games\CreateGame;
use App\Actions\People\EditNameList;
use App\Enums\GameType;
use App\Enums\ScreenTheme;
use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * O10: an example event for local development, demos and staging (P7): 50 names, one pack per
 * game type and a game from each, owned by the first super-admin. Never in production; safe
 * to run again (the demo event is made once).
 *
 *     php artisan db:seed --class=DemoSeeder
 */
final class DemoSeeder extends Seeder
{
    public const string SLUG = 'demo-gathering';

    public function run(CreateEvent $createEvent, EditNameList $names, CreateGame $createGame): void
    {
        SeedContent::refuseProduction(self::class);
        $owner = SeedContent::owner();

        if (Event::withTrashed()->where('slug', self::SLUG)->exists()) {
            $this->command->info('The demo event already exists.');

            return;
        }

        $event = $createEvent->handle($owner, 'Demo Gathering', self::SLUG, ScreenTheme::Dark, false);
        $names->import($event, SeedContent::names(50));

        $packs = [
            SeedContent::pack($owner, GameType::Pentahoot, 'Demo Awards', [
                ['prompt' => 'Most punctual colleague?', 'duration_seconds' => 20],
                ['prompt' => 'Best presenter this year?', 'duration_seconds' => 20],
                ['prompt' => 'Who brings the best snacks?', 'duration_seconds' => 15],
                ['prompt' => 'Most helpful teammate?', 'duration_seconds' => 20],
                ['prompt' => 'Best dressed tonight?', 'duration_seconds' => 15],
            ]),
            SeedContent::pack($owner, GameType::TebakKata, 'Demo Word Guess', [
                ['prompt' => 'Capital of Indonesia', 'answer_text' => 'JAKARTA', 'initial_open_indexes' => [0], 'points' => 1],
                ['prompt' => 'The largest ocean', 'answer_text' => 'PACIFIC', 'initial_open_indexes' => [1], 'points' => 1],
                ['prompt' => 'A bonus: the tallest mountain', 'answer_text' => 'EVEREST', 'initial_open_indexes' => [], 'points' => 2],
            ]),
            SeedContent::pack($owner, GameType::TebakGambar, 'Demo Picture Guess', [
                self::pictureQuestion('Which colour is this?', 'Red', [200, 40, 60]),
                self::pictureQuestion('And this one?', 'Blue', [40, 80, 200]),
            ]),
        ];

        foreach ($packs as $pack) {
            $createGame->handle($event, $pack);
        }

        $this->command->info('Demo event ready at /'.self::SLUG.' (still a draft: open it on its Live control page).');
    }

    /**
     * @param  array{int<0, 255>, int<0, 255>, int<0, 255>}  $rgb
     * @return array<string, mixed>
     */
    private static function pictureQuestion(string $title, string $answer, array $rgb): array
    {
        [$question, $questionSmall] = SeedContent::picture('?', $rgb);
        [$answerImage, $answerSmall] = SeedContent::picture($answer, $rgb);

        return [
            'title' => $title,
            'answer_text' => $answer,
            'points' => 1,
            'question_image' => $question,
            'question_image_small' => $questionSmall,
            'answer_image' => $answerImage,
            'answer_image_small' => $answerSmall,
        ];
    }
}
