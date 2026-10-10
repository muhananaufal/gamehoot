<?php

declare(strict_types=1);

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;

/**
 * K9 (audit CODE-1): every host and admin route names its Policy check in the route file with
 * ->can(), so a sweep of access rights reads one place.
 */
it('names the access check of every host and admin route in the route file (K9)', function (): void {
    // Routes that only reach the signed-in host's own account: nothing to authorize against.
    $ownAccount = [
        'GET host',
        'GET host/events/create',
        'POST host/events',
        'POST host/events/names/parse',
        'GET host/packs',
        'POST host/packs',
        'GET host/session',
        'GET host/trash',
    ];

    $unchecked = collect(Router::getRoutes()->getRoutes())
        ->filter(fn (Route $route): bool => preg_match('#^(host|admin)(/|$)#', $route->uri()) === 1)
        ->reject(fn (Route $route): bool => collect($route->gatherMiddleware())->contains(
            fn (mixed $middleware): bool => is_string($middleware) && str_starts_with($middleware, 'can:'),
        ))
        ->map(fn (Route $route): string => $route->methods()[0].' '.$route->uri())
        ->reject(fn (string $route): bool => in_array($route, $ownAccount, true))
        ->values()
        ->all();

    expect($unchecked)->toBe([]);
});
