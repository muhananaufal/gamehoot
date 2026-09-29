<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Person;
use App\Models\Question;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Factories\Factory;
use InvalidArgumentException;

/**
 * @extends Factory<Vote>
 */
final class VoteFactory extends Factory
{
    /**
     * Define the model's default state. Voter and target belong to the event of the question.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'question_id' => Question::factory(),
            'voter_person_id' => fn (array $attributes): PersonFactory => $this->personOfQuestionEvent($attributes),
            'target_person_id' => fn (array $attributes): PersonFactory => $this->personOfQuestionEvent($attributes),
            'attempt' => 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function personOfQuestionEvent(array $attributes): PersonFactory
    {
        $questionId = $attributes['question_id'];

        if (! is_string($questionId)) {
            throw new InvalidArgumentException('question_id must be resolved before the voter and target.');
        }

        $eventId = Question::query()->findOrFail($questionId)->game()->firstOrFail()->event_id;

        return Person::factory()->state(['event_id' => $eventId]);
    }
}
