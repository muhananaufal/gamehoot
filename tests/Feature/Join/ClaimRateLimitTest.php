<?php

declare(strict_types=1);

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

/**
 * F11 (audit RATE-1): a whole venue may reach the server from one address, and everyone scans
 * the QR code in the same minute, so claims allow 1,000 a minute per IP. A claim is still guarded
 * by the conditional update (F6) and the 16-character personal link tokens.
 */
it('lets a whole venue behind one address claim names in the same minute (F11)', function (): void {
    $limiter = RateLimiter::limiter('claim');
    expect($limiter)->not->toBeNull();

    $limit = $limiter(Request::create('/year-end-party/claim', 'POST', server: ['REMOTE_ADDR' => '203.0.113.7']));

    expect($limit)->toBeInstanceOf(Limit::class)
        ->and($limit->maxAttempts)->toBe(1000)
        ->and($limit->decaySeconds)->toBe(60)
        ->and($limit->key)->toBe('203.0.113.7');
});
