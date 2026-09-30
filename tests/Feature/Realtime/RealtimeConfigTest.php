<?php

declare(strict_types=1);

use App\Exceptions\MissingConfiguration;
use App\Support\RequiredConfig;

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
