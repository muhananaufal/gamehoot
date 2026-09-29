<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
final class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    private static ?string $password = null;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => self::$password ??= Hash::make('password'),
            'is_super_admin' => false,
            'disabled_at' => null,
            'remember_token' => Str::random(10),
        ];
    }

    public function superAdmin(): self
    {
        return $this->state(fn (): array => ['is_super_admin' => true]);
    }

    public function disabled(): self
    {
        return $this->state(fn (): array => ['disabled_at' => now()]);
    }
}
