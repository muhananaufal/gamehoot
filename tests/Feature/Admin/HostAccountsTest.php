<?php

declare(strict_types=1);

use App\Enums\LoggedAction;
use App\Models\ActionLog;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
        ['GET', '/admin/users/{user}/edit'],
        ['PUT', '/admin/users/{user}'],
        ['DELETE', '/admin/users/{user}'],
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
            'username' => 'SariW',
            'email' => 'Sari@Company.test',
            'password' => 'correct-horse-42',
            'password_confirmation' => 'correct-horse-42',
        ])->assertRedirect('/admin/users')->assertSessionHas('status');

        $sari = User::query()->where('email', 'sari@company.test')->firstOrFail();
        expect($sari->is_super_admin)->toBeFalse()
            ->and($sari->username)->toBe('sariw')
            ->and(Hash::check('correct-horse-42', $sari->password))->toBeTrue()
            ->and(loggedActions(LoggedAction::AccountCreated))->toBe(1);

        $log = ActionLog::query()->firstOrFail();
        expect($log->user_id)->toBe($admin->id)
            ->and($log->event_id)->toBeNull()
            ->and($log->payload)->toBe(['account_id' => $sari->id]);
    });

    it('rejects a weak password, a taken email and a taken or badly formed username (C-6)', function (): void {
        $old = User::factory()->create(['email' => 'sari@company.test', 'username' => 'sariw']);
        $old->delete();

        actingAs(superAdmin())->post('/admin/users', [
            'name' => 'Sari Wijaya',
            'username' => 'sariw',
            'email' => 'sari@company.test',
            'password' => 'password',
            'password_confirmation' => 'password',
        ])->assertSessionHasErrors(['username', 'email', 'password']);

        actingAs(superAdmin())->post('/admin/users', ['name' => 'Sari', 'username' => 'sari wijaya'])
            ->assertSessionHasErrors(['username' => 'The username may only hold lower case letters, digits, dots, underscores and hyphens (3-30).']);
    });

    it('shows the Add host modal with a title, full-width fields and a username', function (): void {
        $html = (string) actingAs(superAdmin())->get('/admin/users/create')->getContent();
        $modal = Str::after($html, 'up-main="modal"');

        expect($modal)->toContain('<h2')->toContain('Add host')
            ->toContain('name="username"')
            ->and($modal)->not->toContain('max-w-lg');
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

    // A name stays inside its attribute.
    expect($html)->toContain('data-name="Ann &quot;Q&quot; &lt;b&gt;"');

    expect($html)->toContain('x-data="tableView({ key: \'accounts\', sort: \'name\' })"')
        ->and($html)->toContain('placeholder="Search name or email"')
        ->and($html)->toContain('Rows per page')
        ->and($html)->toMatch('/<tr data-row [^>]*data-search="dina host dina@example\.test" data-filter="active"/')
        ->and($html)->toMatch('/data-filter="disabled"/')
        // U6: Added is its own sortable column; the info cards are gone.
        ->and($html)->toContain('aria-sort="ariaSort(\'added\')"')
        ->and($html)->toContain('1 Sep 2026')
        ->and($html)->not->toContain('Added 1 Sep 2026')
        ->and($html)->not->toContain('transfer them to another host if needed')
        ->and($html)->toContain('data-ask="disable"')
        ->and($html)->not->toContain('@js(');
});

it('sends the Disable choice in the form, never in the address, and sorts every column (U4, U5)', function (): void {
    $admin = User::factory()->superAdmin()->create();
    User::factory()->create();

    $html = (string) actingAs($admin)->get('/admin/users')->getContent();

    // @js() writes quotes as " inside the page.
    expect($html)->not->toContain('disabled=1')
        ->and(str_replace(chr(92).'u0022', '"', $html))->toContain('"data":{"disabled":"1"}');

    foreach (['name', 'email', 'role', 'status', 'added'] as $key) {
        expect($html)->toContain("sortBy('{$key}')")->toContain("data-sort-{$key}=");
    }
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

describe('editing and deleting accounts (C-7)', function (): void {
    it('edits the name, username and email of an account and logs it', function (): void {
        $host = User::factory()->create(['username' => 'old.name']);

        actingAs(superAdmin())->put("/admin/users/{$host->id}", ['name' => 'Arif R', 'username' => 'Arif.R', 'email' => 'ARIF@company.test'])
            ->assertRedirect('/admin/users');

        $host->refresh();
        expect([$host->name, $host->username, $host->email])->toBe(['Arif R', 'arif.r', 'arif@company.test'])
            ->and(loggedActions(LoggedAction::AccountUpdated))->toBe(1);
    });

    it('keeps its own username and email valid when saved unchanged, and refuses those of others', function (): void {
        $host = User::factory()->create(['username' => 'arif', 'email' => 'arif@company.test']);
        User::factory()->create(['username' => 'sari', 'email' => 'sari@company.test']);

        actingAs(superAdmin())->put("/admin/users/{$host->id}", ['name' => 'Arif', 'username' => 'arif', 'email' => 'arif@company.test'])->assertSessionHasNoErrors();
        actingAs(superAdmin())->put("/admin/users/{$host->id}", ['name' => 'Arif', 'username' => 'sari', 'email' => 'sari@company.test'])->assertSessionHasErrors(['username', 'email']);
    });

    it('shows the edit form as a modal with a title', function (): void {
        $host = User::factory()->create(['name' => 'Arif Rahman']);

        $modal = Str::after((string) actingAs(superAdmin())->get("/admin/users/{$host->id}/edit")->assertOk()->getContent(), 'up-main="modal"');

        expect($modal)->toContain('<h2')->toContain('Edit Arif Rahman')->toContain('name="username"');
    });

    it('deletes an account softly: gone from the list and sign in, kept for history, co-hosting ended', function (): void {
        $admin = superAdmin();
        $host = User::factory()->create(['name' => 'Gone Host']);
        $event = Event::factory()->create();
        $event->cohosts()->attach($host);

        actingAs($admin)->delete("/admin/users/{$host->id}")->assertRedirect('/admin/users')->assertSessionHas('status');

        expect(User::query()->find($host->id))->toBeNull()
            ->and(User::withTrashed()->find($host->id))->not->toBeNull()
            ->and($event->cohosts()->count())->toBe(0)
            ->and(loggedActions(LoggedAction::AccountDeleted))->toBe(1);
        // The toast still names it once; the table no longer has its row.
        actingAs($admin)->get('/admin/users')->assertDontSee('data-name="Gone Host"', false);
    });

    it('keeps the name of a deleted account on what it did (E13)', function (): void {
        $host = User::factory()->create(['name' => 'Gone Host']);
        $event = Event::factory()->for($host, 'owner')->create();
        $log = ActionLog::query()->create(['event_id' => $event->id, 'user_id' => $host->id, 'action' => LoggedAction::JoinLocked]);
        $event->forceFill(['owner_id' => User::factory()->create()->id])->save();
        $host->delete();

        expect($log->refresh()->user?->name)->toBe('Gone Host');
    });

    it('refuses to delete an account that still owns events, also deleted ones', function (): void {
        $host = User::factory()->create();
        Event::factory()->for($host, 'owner')->create()->delete();

        actingAs(superAdmin())->delete("/admin/users/{$host->id}")
            ->assertSessionHasErrors(['account' => 'This host still owns events. Transfer them to another host first.']);

        expect(User::query()->find($host->id))->not->toBeNull();
    });

    it('never deletes your own account', function (): void {
        $admin = superAdmin();

        actingAs($admin)->delete("/admin/users/{$admin->id}")->assertForbidden();
    });
});
