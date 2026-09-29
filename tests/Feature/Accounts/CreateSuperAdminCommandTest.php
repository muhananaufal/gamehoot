<?php

declare(strict_types=1);

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Testing\PendingCommand;

use function Pest\Laravel\artisan;

uses(RefreshDatabase::class);

function createSuperAdminCommand(): PendingCommand
{
    $command = artisan('pentahoot:create-super-admin');

    // artisan() returns an exit code only when console output mocking is disabled.
    if (! $command instanceof PendingCommand) {
        throw new LogicException('Console output mocking must stay enabled for this test.');
    }

    return $command;
}

describe('O10 pentahoot:create-super-admin', function (): void {
    it('creates a super-admin from answers typed at the prompt', function (): void {
        createSuperAdminCommand()
            ->expectsQuestion('Name', 'Dina')
            ->expectsQuestion('Email', 'dina@company.test')
            ->expectsQuestion('Password (at least 12 characters, letters and digits)', 'correct-horse-42')
            ->expectsQuestion('Repeat the password', 'correct-horse-42')
            ->expectsOutputToContain('Super-admin dina@company.test created.')
            ->assertSuccessful();

        $user = User::query()->where('email', 'dina@company.test')->firstOrFail();
        expect($user->is_super_admin)->toBeTrue()
            ->and(Hash::check('correct-horse-42', $user->password))->toBeTrue()
            ->and(ActionLog::query()->where('action', LoggedAction::AccountCreated)->count())->toBe(1);
    });

    it('rejects a weak password and creates nothing', function (): void {
        createSuperAdminCommand()
            ->expectsQuestion('Name', 'Dina')
            ->expectsQuestion('Email', 'dina@company.test')
            ->expectsQuestion('Password (at least 12 characters, letters and digits)', 'short1')
            ->expectsQuestion('Repeat the password', 'short1')
            ->expectsOutputToContain('The password field must be at least 12 characters.')
            ->assertFailed();

        expect(User::query()->count())->toBe(0);
    });

    it('rejects passwords that do not match', function (): void {
        createSuperAdminCommand()
            ->expectsQuestion('Name', 'Dina')
            ->expectsQuestion('Email', 'dina@company.test')
            ->expectsQuestion('Password (at least 12 characters, letters and digits)', 'correct-horse-42')
            ->expectsQuestion('Repeat the password', 'correct-horse-43')
            ->expectsOutputToContain('The password field confirmation does not match.')
            ->assertFailed();

        expect(User::query()->count())->toBe(0);
    });

    it('rejects an email that already has an account', function (): void {
        User::factory()->create(['email' => 'dina@company.test']);

        createSuperAdminCommand()
            ->expectsQuestion('Name', 'Dina')
            ->expectsQuestion('Email', 'DINA@company.test')
            ->expectsQuestion('Password (at least 12 characters, letters and digits)', 'correct-horse-42')
            ->expectsQuestion('Repeat the password', 'correct-horse-42')
            ->expectsOutputToContain('The email has already been taken.')
            ->assertFailed();

        expect(User::query()->count())->toBe(1);
    });
});
