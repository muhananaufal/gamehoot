<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Actions\Events\CreateEvent;
use App\Actions\Events\OpenEvent;
use App\Actions\Games\CreateGame;
use App\Actions\People\EditNameList;
use App\Enums\GameType;
use App\Enums\ScreenTheme;
use App\Models\Event;
use Illuminate\Database\Seeder;

/**
 * O10, F19: the event the load test script plays on staging: 500 names (the B-3 limit) and an
 * open event with a ten-question Pentahoot game. Never in production; safe to run again.
 *
 *     php artisan db:seed --class=LoadTestSeeder
 */
final class LoadTestSeeder extends Seeder
{
    public const string SLUG = 'load-test';

    public function run(CreateEvent $createEvent, EditNameList $names, CreateGame $createGame, OpenEvent $openEvent): void
    {
        SeedContent::refuseProduction(self::class);
        $owner = SeedContent::owner();

        if (Event::withTrashed()->where('slug', self::SLUG)->exists()) {
            $this->command->info('The load-test event already exists.');

            return;
        }

        $event = $createEvent->handle($owner, 'Load Test', self::SLUG, ScreenTheme::Dark, false);
        $names->import($event, SeedContent::names(500));

        $questions = array_map(
            fn (int $number): array => ['prompt' => "Load test question {$number}?", 'duration_seconds' => 20],
            range(1, 10),
        );
        $createGame->handle($event, SeedContent::pack($owner, GameType::Pentahoot, 'Load Test Awards', $questions));
        $openEvent->handle($event, $owner);

        $this->command->info('Load-test event open at /'.self::SLUG.' with 500 names.');
    }
}
