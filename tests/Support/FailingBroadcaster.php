<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Broadcasting\Broadcaster;
use Illuminate\Http\Request;

/**
 * Stands in for a Reverb server that is down (T2, F22).
 */
final class FailingBroadcaster implements Broadcaster
{
    /**
     * @param  Request  $request
     */
    public function auth($request): mixed
    {
        return null;
    }

    /**
     * @param  Request  $request
     */
    public function validAuthenticationResponse($request, $result): mixed
    {
        return null;
    }

    /**
     * @param  array<int, mixed>  $channels
     * @param  string  $event
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        throw new BroadcastException('Reverb is not reachable.');
    }
}
