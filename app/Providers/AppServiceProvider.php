<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

final class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void {}

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // O7: host account passwords.
        Password::defaults(fn (): Password => Password::min(12)->letters()->numbers());

        // F11: loose per IP because a whole venue may share one address. Failed attempts per
        // account are limited more strictly in LoginRequest.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?? 'unknown'));
    }
}
