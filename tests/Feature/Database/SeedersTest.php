<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

it('seeds only base data that is safe to run again, and never an account (O10)', function (): void {
    expect(Artisan::call('db:seed', ['--force' => true]))->toBe(0)
        ->and(Artisan::call('db:seed', ['--force' => true]))->toBe(0);

    // Accounts come from pentahoot:create-super-admin, never from a seeder with a known password.
    expect(User::query()->count())->toBe(0);
});
