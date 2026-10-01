<?php

declare(strict_types=1);

use App\Exceptions\MissingConfiguration;
use App\Support\RequiredConfig;
use GuzzleHttp\Client;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Support\Arr;

it('names every missing Reverb variable when broadcasting through Reverb (K6)', function (): void {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => '',
        'broadcasting.connections.reverb.secret' => null,
        'broadcasting.connections.reverb.app_id' => '1',
        'broadcasting.connections.reverb.options.host' => 'reverb',
        'broadcasting.connections.reverb.client.host' => null,
    ]);

    expect(fn () => RequiredConfig::check())->toThrow(
        MissingConfiguration::class,
        'Missing required configuration: REVERB_APP_KEY, REVERB_APP_SECRET, REVERB_CLIENT_HOST. Set them in .env (see .env.example).',
    );
});

it('requires nothing extra for the log and null broadcasters', function (string $driver): void {
    config(['broadcasting.default' => $driver, 'broadcasting.connections.reverb.key' => null]);

    RequiredConfig::check();

    expect(RequiredConfig::missing())->toBe([]);
})->with(['log', 'null']);

it('gives up on a stuck Reverb within seconds instead of holding the PHP worker (F22)', function (): void {
    config(['broadcasting.connections.reverb.key' => 'key', 'broadcasting.connections.reverb.secret' => 'secret', 'broadcasting.connections.reverb.app_id' => '1']);
    $config = config('broadcasting.connections.reverb');
    expect($config)->toBeArray();

    /** @var array<string, mixed> $config */
    $pusher = app(BroadcastManager::class)->pusher($config);
    $guzzle = (new ReflectionProperty($pusher, 'client'))->getValue($pusher);
    assert($guzzle instanceof Client);

    expect($pusher->getSettings()['timeout'])->toBeLessThanOrEqual(2)
        ->and($guzzle->getConfig('connect_timeout'))->toBeLessThanOrEqual(1)
        ->and($guzzle->getConfig('timeout'))->toBeLessThanOrEqual(2);
});

it('keeps every Reverb server option the installed package reads (no config drift)', function (): void {
    /** @var array<string, mixed> $package */
    $package = require base_path('vendor/laravel/reverb/config/reverb.php');
    $ours = Arr::dot((array) config('reverb'));

    // F7: no Redis scaling and no socket rate limit, so their sub-options are left out on purpose.
    $missing = collect(array_keys(Arr::dot($package)))
        ->reject(fn (string $key): bool => (bool) preg_match('/\.(scaling|rate_limiting)\./', $key) || str_starts_with($key, 'apps.apps.'))
        ->reject(fn (string $key): bool => array_key_exists($key, $ours))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
