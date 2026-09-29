<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\EventStatus;
use App\Enums\ScreenTheme;
use App\Models\Event;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
final class EventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company().' Gathering';

        return [
            'owner_id' => User::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => EventStatus::Draft,
            'show_on_devices' => false,
            'screen_theme' => ScreenTheme::Dark,
            'state_version' => 0,
        ];
    }

    public function open(): self
    {
        return $this->state(fn (): array => ['status' => EventStatus::Open]);
    }

    public function finished(): self
    {
        return $this->state(fn (): array => ['status' => EventStatus::Finished]);
    }
}
