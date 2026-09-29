<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Event;
use App\Models\Person;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Person>
 */
final class PersonFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->name();

        return [
            'event_id' => Event::factory(),
            'name' => $name,
            'name_normalized' => Str::lower($name),
            'join_token' => Str::random(40),
            'claim_token_hash' => null,
            'claimed_at' => null,
        ];
    }
}
