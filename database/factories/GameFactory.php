<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\GameStatus;
use App\Enums\GameType;
use App\Models\Event;
use App\Models\Game;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Game>
 */
final class GameFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'type' => GameType::Pentahoot,
            'title' => rtrim(fake()->sentence(3), '.'),
            'position' => fake()->numberBetween(1, 20),
            'status' => GameStatus::Draft,
        ];
    }

    public function ofType(GameType $type): self
    {
        return $this->state(fn (): array => ['type' => $type]);
    }
}
