<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify registers the password reset routes without a rate limit, so this limits them per
 * IP address: asking for links can't be used to flood inboxes, and reset tokens can't be guessed.
 */
class LimitPasswordResetAttempts
{
    public const ATTEMPTS_PER_MINUTE = 5;

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('POST') || ! $request->routeIs('password.email', 'password.update')) {
            return $next($request);
        }

        $key = 'password-reset|'.$request->route()->getName().'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::ATTEMPTS_PER_MINUTE)) {
            throw ValidationException::withMessages([
                'email' => __('Too many attempts. Please try again in :seconds seconds.', [
                    'seconds' => RateLimiter::availableIn($key),
                ]),
            ]);
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}
