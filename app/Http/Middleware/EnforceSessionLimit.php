<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * C-8 (audit SESS-1): a host session ends 8 hours after sign in, however active it stays.
 * OWASP Session Management: the limit is enforced on the server; an open host page keeps the
 * idle session alive (GET /host/session) and warns before this limit.
 */
final class EnforceSessionLimit
{
    /** When the session signed in, as a Unix timestamp. */
    public const string STARTED_AT = 'auth_started_at';

    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user() instanceof User) {
            return $next($request);
        }

        $started = $request->session()->get(self::STARTED_AT);

        // A session signed in before this limit existed starts counting now.
        if (! is_int($started)) {
            $request->session()->put(self::STARTED_AT, now()->getTimestamp());

            return $next($request);
        }

        if (now()->getTimestamp() >= $started + self::limitSeconds()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                throw new AuthenticationException;
            }

            return redirect()->route('login')->withErrors(['login' => __('accounts.session_ended')]);
        }

        return $next($request);
    }

    /**
     * When the current session ends, in milliseconds.
     */
    public static function endsAt(Request $request): int
    {
        $started = $request->session()->get(self::STARTED_AT);

        return ((is_int($started) ? $started : now()->getTimestamp()) + self::limitSeconds()) * 1_000;
    }

    private static function limitSeconds(): int
    {
        return config()->integer('session.absolute_lifetime') * 60;
    }
}
