<?php

declare(strict_types=1);

namespace App\Support;

use App\Exceptions\MissingConfiguration;

/**
 * K6: variables the app cannot run without. Checked when the app boots for web requests,
 * so a missing value stops it with a clear message instead of failing later at the venue.
 */
final class RequiredConfig
{
    /**
     * Config key => environment variable, required while broadcasting through Reverb.
     */
    private const array REVERB = [
        'broadcasting.connections.reverb.key' => 'REVERB_APP_KEY',
        'broadcasting.connections.reverb.secret' => 'REVERB_APP_SECRET',
        'broadcasting.connections.reverb.app_id' => 'REVERB_APP_ID',
        'broadcasting.connections.reverb.options.host' => 'REVERB_HOST',
        'broadcasting.connections.reverb.client.host' => 'REVERB_CLIENT_HOST',
    ];

    /**
     * @throws MissingConfiguration
     */
    public static function check(): void
    {
        $missing = self::missing();

        if ($missing !== []) {
            throw MissingConfiguration::for($missing);
        }
    }

    /**
     * @return list<string> the environment variables that are empty
     */
    public static function missing(): array
    {
        if (config('broadcasting.default') !== 'reverb') {
            return [];
        }

        $missing = [];

        foreach (self::REVERB as $key => $variable) {
            $value = config($key);

            if (! is_scalar($value) || (string) $value === '') {
                $missing[] = $variable;
            }
        }

        return $missing;
    }
}
