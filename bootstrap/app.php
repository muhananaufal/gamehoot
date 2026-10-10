<?php

declare(strict_types=1);

use App\Exceptions\ActionRefused;
use App\Exceptions\ClaimRefused;
use App\Exceptions\NameListLocked;
use App\Http\ApiError;
use App\Http\Middleware\AddLogContext;
use App\Http\Middleware\EnforceSessionLimit;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\SecurityHeaders;
use App\Support\QueryErrorLog;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // F2: POST /broadcasting/auth for the host channel, behind the same guards as host pages.
    ->withBroadcasting(__DIR__.'/../routes/channels.php', [
        'middleware' => ['web', 'auth', 'auth.session', EnsureAccountIsActive::class, EnforceSessionLimit::class],
    ])
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectUsersTo('/host');

        // O7: on every response, including 404s for unknown paths that match no route.
        $middleware->append(SecurityHeaders::class);
        // K5: after route model binding, so the event id is known.
        $middleware->web(append: [AddLogContext::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // K5: a refused action is normal play (late vote, double click), not an error to log.
        $exceptions->dontReport([ActionRefused::class, ClaimRefused::class, NameListLocked::class]);

        // K5 (audit SEC-9): a failed query is logged without the values it carried.
        $exceptions->report(function (QueryException $exception): void {
            Log::error('Database query failed.', QueryErrorLog::context($exception));
        })->stop();

        // K4: {code, message} for framework errors too.
        $exceptions->render(
            fn (Throwable $exception, Request $request) => $request->expectsJson()
                ? ApiError::render($exception, (bool) config('app.debug'))
                : null,
        );
    })->create();
