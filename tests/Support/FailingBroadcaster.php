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
        // Pusher puts the signed request URL into its message.
        throw new BroadcastException('Pusher error: cURL error 7: Failed to connect to reverb port 8080 for http://reverb:8080/apps/1/events?auth_key=key&auth_signature=secret-signature&body_md5=abc.');
    }
}
