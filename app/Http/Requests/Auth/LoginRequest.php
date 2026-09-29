<?php

declare(strict_types=1);

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
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
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * @throws ValidationException
     */
    public function authenticate(): void
    {
        $this->ensureIsNotRateLimited();

        $credentials = ['email' => $this->email(), 'password' => $this->string('password')->toString()];

        if (! Auth::validate($credentials)) {
            RateLimiter::hit($this->throttleKey());

            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $user = User::query()->where('email', $this->email())->firstOrFail();

        // C-5: a disabled account keeps its data but cannot sign in.
        if ($user->disabled_at !== null) {
            throw ValidationException::withMessages(['email' => __('accounts.disabled')]);
        }

        Auth::login($user);
        RateLimiter::clear($this->throttleKey());
    }

    private function email(): string
    {
        return Str::lower($this->string('email')->trim()->toString());
    }

    /**
     * @throws ValidationException
     */
    private function ensureIsNotRateLimited(): void
    {
        if (! RateLimiter::tooManyAttempts($this->throttleKey(), self::MAX_FAILED_ATTEMPTS)) {
            return;
        }

        event(new Lockout($this));

        throw ValidationException::withMessages([
            'email' => __('auth.throttle', ['seconds' => RateLimiter::availableIn($this->throttleKey())]),
        ]);
    }

    private function throttleKey(): string
    {
        return 'login:'.$this->email();
    }
}
