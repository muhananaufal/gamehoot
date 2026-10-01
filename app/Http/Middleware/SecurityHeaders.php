<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * O7: protective headers on every page. Everything is served from the app itself (fonts
 * included), so the policy only adds the Reverb socket and, in development, the Vite server.
 * 'unsafe-eval' stays because Alpine evaluates its x-* expressions with AsyncFunction; inline
 * <script> still needs the per-request nonce, so injected markup cannot run code.
 */
final class SecurityHeaders
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();
        $response = $next($request);

        $headers = [
            'Content-Security-Policy' => $this->policy($nonce),
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            // Personal links carry a token in the path; it never leaves the site in a Referer.
            'Referrer-Policy' => 'same-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=()',
        ];

        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        foreach ($headers as $name => $value) {
            $response->headers->set($name, $value, replace: false);
        }

        return $response;
    }

    private function policy(string $nonce): string
    {
        $dev = $this->viteDevServer();
        $socket = $this->socketOrigin();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", "'unsafe-eval'", ...$dev],
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'font-src' => ["'self'", ...$dev],
            // Picked images are previewed from blob: and data: URLs before upload (E8).
            'img-src' => ["'self'", 'data:', 'blob:'],
            'connect-src' => ["'self'", ...($socket === null ? [] : [$socket]), ...$dev, ...array_map(fn (string $origin): string => preg_replace('/^http/', 'ws', $origin) ?? $origin, $dev)],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return collect($directives)->map(fn (array $sources, string $directive): string => $directive.' '.implode(' ', $sources))->implode('; ');
    }

    /**
     * The WebSocket origin browsers use for Reverb, from the runtime config (P1).
     */
    private function socketOrigin(): ?string
    {
        $host = config('broadcasting.connections.reverb.client.host');

        if (! is_string($host) || $host === '') {
            return null;
        }

        $scheme = config('broadcasting.connections.reverb.client.scheme') === 'https' ? 'wss' : 'ws';
        $port = config('broadcasting.connections.reverb.client.port');

        return "{$scheme}://{$host}:".(is_numeric($port) ? (int) $port : 443);
    }

    /**
     * @return list<string>
     */
    private function viteDevServer(): array
    {
        if (! Vite::isRunningHot()) {
            return [];
        }

        $url = trim(File::get(Vite::hotFile()));

        return $url === '' ? [] : [$url];
    }
}
