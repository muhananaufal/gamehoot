<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Enums\GameType;
use App\Enums\QuestionStatus;
use App\Models\Event;
use App\Models\Game;
use App\Models\PackQuestion;
use App\Models\Person;
use App\Models\Question;
use App\Models\QuestionPack;
use App\Models\User;

/**
 * Builds Pentahoot packs, events and games for tests.
 */
final class Pentahoot
{
    /**
     * @param  list<string>  $prompts
     */
    public static function pack(User $owner, array $prompts = ['Most punctual?', 'Best presenter?'], string $title = 'Office Awards'): QuestionPack
    {
        $pack = new QuestionPack(['title' => $title, 'game_type' => GameType::Pentahoot]);
        $pack->owner()->associate($owner)->save();

        foreach ($prompts as $index => $prompt) {
            $question = new PackQuestion(['position' => $index + 1, 'points' => 1]);
            $question->pack()->associate($pack)->save();
            $question->pentahoot()->create(['prompt' => $prompt, 'duration_seconds' => 20]);
        }

        return $pack;
    }

    /**
     * An open event with claimed names, ready to play.
     *
     * @param  list<string>  $names
     * @param  array<string, mixed>  $attributes
     */
    public static function event(array $names = ['Rita Wulandari', 'Budi Santoso', 'Ana Putri'], array $attributes = []): Event
    {
        $event = Event::factory()->open()->create(['name' => 'Year-End Party', 'slug' => 'year-end-party', ...$attributes]);

        foreach ($names as $name) {
            Person::factory()->for($event)->claimed()->create(['name' => $name]);
        }

        return $event;
    }

    /**
     * @param  list<string>  $prompts
     */
    public static function game(Event $event, array $prompts = ['Most punctual?', 'Best presenter?'], string $title = 'Office Awards'): Game
    {
        $last = $event->games()->max('position');
        $position = is_numeric($last) ? (int) $last + 1 : 1;
        $game = Game::factory()->for($event)->create(['type' => GameType::Pentahoot, 'title' => $title, 'position' => $position]);

        foreach ($prompts as $index => $prompt) {
            $question = Question::factory()->for($game)->create(['position' => $index + 1, 'status' => QuestionStatus::Ready]);
            $question->pentahoot()->create(['prompt' => $prompt, 'duration_seconds' => 20]);
        }

        return $game;
    }

    /**
     * A game that is running (D-1), with the names list locked (B-3).
     *
     * @param  list<string>  $prompts
     */
    public static function running(Event $event, array $prompts = ['Most punctual?', 'Best presenter?']): Game
    {
        $game = self::game($event, $prompts);
        $event->forceFill(['active_game_id' => $game->id, 'names_locked_at' => now()])->save();

        return $game;
    }

    public static function question(Game $game, int $number = 1): Question
    {
        return $game->questions()->where('position', $number)->firstOrFail();
    }

    public static function person(Event $event, string $name): Person
    {
        return $event->people()->where('name', $name)->firstOrFail();
    }
}
