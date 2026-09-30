<?php

declare(strict_types=1);

namespace App\Providers;

use App\Support\RequiredConfig;
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
        // K6: web requests need the realtime configuration. Console commands still run, so
        // setup steps such as key:generate work on a fresh .env.
        if (! $this->app->runningInConsole()) {
            RequiredConfig::check();
        }

        // O7: host account passwords.
        Password::defaults(fn (): Password => Password::min(12)->letters()->numbers());

        // F11: loose per IP because a whole venue may share one address. Failed attempts per
        // account are limited more strictly in LoginRequest.
        RateLimiter::for('login', fn (Request $request): Limit => Limit::perMinute(30)->by($request->ip() ?? 'unknown'));

        // F11: a whole venue may share one address, so the per-IP claim limit is generous.
        RateLimiter::for('claim', fn (Request $request): Limit => Limit::perMinute(300)->by($request->ip() ?? 'unknown'));
    }
}
