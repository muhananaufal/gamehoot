<?php

declare(strict_types=1);

/**
 * K9 (audit CODE-1): access rights live in one layer, the route file (tests/Feature/Http/
 * RouteAuthorizationTest.php checks every host and admin route); FormRequests only validate.
 */
arch('form requests only validate, they do not decide access (K9)')
    ->expect('App\Http\Requests')
    ->not->toHaveMethod('authorize');
