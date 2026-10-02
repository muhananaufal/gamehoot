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

it('lets a super-admin search and filter host accounts, see when each was added, and confirm Disable (U4, U5, U6)', function (): void {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create(['name' => 'Dina Host', 'email' => 'dina@example.test', 'created_at' => '2026-09-01 03:00:00']);
    User::factory()->create(['name' => 'Old Host', 'disabled_at' => now()]);
    User::factory()->create(['name' => 'Ann "Q" <b>']);

    $html = (string) actingAs($admin)->get('/admin/users')->assertOk()->getContent();

    // A name inside the click handler stays inside its attribute and its JS string.
    expect($html)->toContain('askFor(\'disable\', \'Ann \u0022Q\u0022 \u003Cb\u003E\', ');

    expect($html)->toContain('x-data="tableView({ key: \'accounts\', sort: \'name\' })"')
        ->and($html)->toContain('aria-label="Search accounts"')
        ->and($html)->toContain('Rows per page')
        ->and($html)->toMatch('/<tr data-row data-search="dina host dina@example\.test" data-filter="active" data-sort-name="Dina Host" data-sort-added="\d+"/')
        ->and($html)->toMatch('/data-filter="disabled"/')
        // U6: Added is its own sortable column; the info cards are gone.
        ->and($html)->toContain('aria-sort="ariaSort(\'added\')"')
        ->and($html)->toContain('1 Sep 2026')
        ->and($html)->not->toContain('Added 1 Sep 2026')
        ->and($html)->not->toContain('transfer them to another host if needed')
        ->and($html)->toContain('$store.confirm.askFor(\'disable\', \'Dina Host\', ')
        ->and($html)->not->toContain('@js(');
});

it('disables an account from the confirmation dialog, which sends the choice in the address (U5)', function (): void {
    $admin = User::factory()->superAdmin()->create();
    $host = User::factory()->create();

    actingAs($admin)->put("/admin/users/{$host->id}/status?disabled=1")->assertRedirect();

    expect($host->refresh()->disabled_at)->not->toBeNull();
});

it('marks your own account next to your name and resets passwords in a dialog (U5, U6)', function (): void {
    $admin = User::factory()->superAdmin()->create(['name' => 'Muha Admin']);
    $host = User::factory()->create(['name' => 'Dina Host']);

    $html = (string) actingAs($admin)->get('/admin/users')->assertOk()->getContent();
    expect($html)->toMatch('/Muha Admin<\/span>\s*<span[^>]*>You<\/span>/')
        ->and($html)->toContain('Reset password for Dina Host')
        ->and($html)->toContain("id=\"reset-{$host->id}-password\"");

    // A rejected password reopens that account's dialog with the error.
    actingAs($admin)->from('/admin/users')->followingRedirects()
        ->put("/admin/users/{$host->id}/password", ['password' => 'short', 'password_confirmation' => 'short', 'resetting' => $host->id])
        ->assertSee('x-init=" $refs.dialog.showModal() "', false);
});
