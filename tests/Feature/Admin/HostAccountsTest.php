<?php

declare(strict_types=1);

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

uses(RefreshDatabase::class);

function superAdmin(): User
{
    return User::factory()->superAdmin()->create(['name' => 'Dina (HR)']);
}

function loggedActions(LoggedAction $action): int
{
    return ActionLog::query()->where('action', $action)->count();
}

describe('C-1, C-4 access', function (): void {
    it('is closed to hosts who are not super-admins', function (string $method, string $uri): void {
        $host = User::factory()->create();
        $other = User::factory()->create();

        actingAs($host)
            ->call($method, str_replace('{user}', $other->id, $uri))
            ->assertForbidden();
    })->with([
        ['GET', '/admin/users'],
        ['GET', '/admin/users/create'],
        ['POST', '/admin/users'],
        ['GET', '/admin/users/{user}/password/edit'],
        ['PUT', '/admin/users/{user}/password'],
        ['PUT', '/admin/users/{user}/status'],
    ]);

    it('is closed to guests', function (): void {
        get('/admin/users')->assertRedirect('/login');
    });
});

describe('host accounts', function (): void {
    it('lists every account with its role and status', function (): void {
        $admin = superAdmin();
        User::factory()->create(['name' => 'Arif Rahman']);
        User::factory()->disabled()->create(['name' => 'Tomi Pratama']);

        actingAs($admin)->get('/admin/users')
            ->assertOk()
            ->assertSeeInOrder(['Arif Rahman', 'Dina (HR)', 'Tomi Pratama'])
            ->assertSee('Super-admin')
            ->assertSee('Disabled');
    });

    it('creates a host account and logs it (E13)', function (): void {
        $admin = superAdmin();

        actingAs($admin)->post('/admin/users', [
            'name' => 'Sari Wijaya',
            'email' => 'Sari@Company.test',
            'password' => 'correct-horse-42',
            'password_confirmation' => 'correct-horse-42',
        ])->assertRedirect('/admin/users')->assertSessionHas('status');

        $sari = User::query()->where('email', 'sari@company.test')->firstOrFail();
        expect($sari->is_super_admin)->toBeFalse()
            ->and(Hash::check('correct-horse-42', $sari->password))->toBeTrue()
            ->and(loggedActions(LoggedAction::AccountCreated))->toBe(1);

        $log = ActionLog::query()->firstOrFail();
        expect($log->user_id)->toBe($admin->id)
            ->and($log->event_id)->toBeNull()
            ->and($log->payload)->toBe(['account_id' => $sari->id]);
    });

    it('rejects a weak password and a taken email', function (): void {
        User::factory()->create(['email' => 'sari@company.test']);

        actingAs(superAdmin())->post('/admin/users', [
            'name' => 'Sari Wijaya',
            'email' => 'sari@company.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['email', 'password']);
    });

    it('resets a password and logs it (C-5, E13)', function (): void {
        $host = User::factory()->create();

        actingAs(superAdmin())->put("/admin/users/{$host->id}/password", [
            'password' => 'temporary-pass-7',
            'password_confirmation' => 'temporary-pass-7',
        ])->assertRedirect('/admin/users');

        expect(Hash::check('temporary-pass-7', $host->refresh()->password))->toBeTrue()
            ->and(loggedActions(LoggedAction::PasswordReset))->toBe(1);
    });

    it('disables and enables a host and logs both (E13)', function (): void {
        $admin = superAdmin();
        $host = User::factory()->create();

        actingAs($admin)->put("/admin/users/{$host->id}/status", ['disabled' => '1'])
            ->assertRedirect('/admin/users');
        expect($host->refresh()->disabled_at)->not->toBeNull();

        actingAs($admin)->put("/admin/users/{$host->id}/status", ['disabled' => '0']);
        expect($host->refresh()->disabled_at)->toBeNull()
            ->and(loggedActions(LoggedAction::AccountDisabled))->toBe(1)
            ->and(loggedActions(LoggedAction::AccountEnabled))->toBe(1);
    });

    it('does not let a super-admin disable their own account', function (): void {
        $admin = superAdmin();

        actingAs($admin)->put("/admin/users/{$admin->id}/status", ['disabled' => '1'])
            ->assertForbidden();

        expect($admin->refresh()->disabled_at)->toBeNull();
    });
});
