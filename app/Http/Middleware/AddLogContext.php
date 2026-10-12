<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Event;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * K5: every log line of a request carries its request id, and the event id when the route
 * has an event. Never names or tokens. The id is returned in X-Request-Id, so a host can
 * quote it and the matching log lines are found.
 */
final class AddLogContext
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) Str::uuid();
        Context::add('request_id', $requestId);

        $event = $request->route('event');

        if ($event instanceof Event) {
            Context::add('event_id', $event->id);
        }

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
