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
 * C-5: an account disabled by a super-admin is signed out on its next request. Audit SEC-7: a
 * JSON request (Live control, channel auth) gets the K4 error UNAUTHENTICATED, so the page shows
 * a message instead of silently following a redirect.
 */
final class EnsureAccountIsActive
{
    /**
     * @param  Closure(Request): Response  $next
     *
     * @throws AuthenticationException
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->disabled_at !== null) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                throw new AuthenticationException;
            }

            // Under the sign in form's only text field, so the reason is shown.
            return redirect()->route('login')->withErrors(['login' => __('accounts.disabled')]);
        }

        return $next($request);
    }
}
