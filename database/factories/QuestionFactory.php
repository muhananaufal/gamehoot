<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QuestionStatus;
use App\Models\Game;
use App\Models\Question;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Question>
 */
final class QuestionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'game_id' => Game::factory(),
            'position' => fake()->numberBetween(1, 50),
            'points' => 1,
            'status' => QuestionStatus::Ready,
            'skip_used' => false,
        ];
    }
}
