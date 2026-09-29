<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertAuthenticatedAs;
use function Pest\Laravel\assertGuest;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

uses(RefreshDatabase::class);

function hostWithPassword(string $password = 'correct-horse-42'): User
{
    return User::factory()->create(['email' => 'arif@company.test', 'password' => $password]);
}

it('sends the root and guests to the login page', function (): void {
    get('/')->assertRedirect('/host');
    get('/host')->assertRedirect('/login');
});

it('shows the sign in form without a register or email reset link (C-5)', function (): void {
    get('/login')
        ->assertOk()
        ->assertSee('Host sign in')
        ->assertSee('Ask your super-admin to reset it.')
        ->assertDontSee('Register')
        ->assertDontSee('Forgot your password?</a>', false);
});

it('signs a host in and sends them to the dashboard', function (): void {
    $host = hostWithPassword();

    post('/login', ['email' => 'ARIF@company.test', 'password' => 'correct-horse-42'])
        ->assertRedirect('/host');

    assertAuthenticatedAs($host);
});

it('rejects a wrong password without saying which field was wrong', function (): void {
    hostWithPassword();

    from('/login')->post('/login', ['email' => 'arif@company.test', 'password' => 'wrong-password-1'])
        ->assertRedirect('/login')
        ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);

    assertGuest();
});

it('refuses a disabled account (C-5)', function (): void {
    $host = hostWithPassword();
    $host->forceFill(['disabled_at' => now()])->save();

    post('/login', ['email' => 'arif@company.test', 'password' => 'correct-horse-42'])
        ->assertSessionHasErrors(['email' => 'This account is disabled. Ask your super-admin.']);

    assertGuest();
});

it('signs out a host whose account was disabled during the session', function (): void {
    $host = User::factory()->create();
    actingAs($host)->get('/host')->assertOk();

    $host->forceFill(['disabled_at' => now()])->save();

    get('/host')->assertRedirect('/login');
    assertGuest();
});

it('limits sign in attempts per email (F11)', function (): void {
    hostWithPassword();

    foreach (range(1, 5) as $attempt) {
        post('/login', ['email' => 'arif@company.test', 'password' => "wrong-password-{$attempt}"]);
    }

    post('/login', ['email' => 'arif@company.test', 'password' => 'correct-horse-42'])
        ->assertSessionHasErrors('email');

    assertGuest();
});

it('signs a host out', function (): void {
    actingAs(User::factory()->create())
        ->post('/logout')
        ->assertRedirect('/login');

    assertGuest();
});
