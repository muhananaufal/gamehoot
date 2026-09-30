<?php

declare(strict_types=1);

use App\Http\Middleware\EnsureAccountIsActive;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // F2: POST /broadcasting/auth for the host channel, behind the same guards as host pages.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => ['web', 'auth', 'auth.session', EnsureAccountIsActive::class],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/host');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
