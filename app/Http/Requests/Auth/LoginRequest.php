<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Accounts\FindAccount;
use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class LoginRequest extends FormRequest
{
    /** F11: failed attempts allowed per account per minute. */
    private const int MAX_FAILED_ATTEMPTS = 5;

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            // C-6: a username or an email in one field.
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $user = FindAccount::byLogin($this->string('login')->toString());
        $this->ensureIsNotRateLimited($user);

        if ($user === null || ! Hash::check($this->string('password')->toString(), $user->password)) {
            RateLimiter::hit($this->throttleKey($user));

            throw ValidationException::withMessages(['login' => __('auth.failed')]);
        }

        // C-5: a disabled account keeps its data but cannot sign in.
        if ($user->disabled_at !== null) {
            throw ValidationException::withMessages(['login' => __('accounts.disabled')]);
        }

        Auth::login($user);
        RateLimiter::clear($this->throttleKey($user));
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(?User $user): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey($user), self::MAX_FAILED_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'login' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey($user))]),
        ]);
    }

    /**
     * C-6, F11: attempts count per account when it exists, so switching between its email and
     * username gives no extra tries; otherwise per typed text.
     */
    private function throttleKey(?User $user): string
    {
        return 'login:'.($user !== null ? $user->id : Str::lower($this->string('login')->trim()->toString()));
    }
}
