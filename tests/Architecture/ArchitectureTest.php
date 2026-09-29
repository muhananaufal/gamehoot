<?php

declare(strict_types=1);

// K1: architecture rules from AGENTS.md, enforced as tests.
// Pest's "strict" preset is not used as a whole: it also forbids protected methods,
// which Eloquent requires (for example Model::casts()). Its rules that fit Laravel
// are listed one by one below instead.

arch('php preset')->preset()->php();

arch('security preset')->preset()->security();

arch('laravel preset')->preset()->laravel();

arch('application code declares strict types')
    ->expect('App')
    ->toUseStrictTypes();

arch('application code uses strict equality')
    ->expect('App')
    ->toUseStrictEquality();

arch('application classes are final')
    ->expect('App')
    ->classes()
    ->toBeFinal();

arch('debug and blocking helpers are not used')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'sleep', 'usleep'])
    ->not->toBeUsed();

// K1, F13: game engines are plain domain code, callable from HTTP, console and tests alike.
arch('game engines do not depend on HTTP classes')
    ->expect('App\Games')
    ->not->toUse(['App\Http', 'Illuminate\Http', 'Illuminate\Routing', 'Illuminate\Support\Facades\Request']);

arch('controllers do not query the database directly')
    ->expect('App\Http\Controllers')
    ->not->toUse('Illuminate\Support\Facades\DB');
