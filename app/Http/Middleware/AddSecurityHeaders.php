<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AddSecurityHeaders
{
    private const int ONE_YEAR_IN_SECONDS = 31_536_000;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $response->headers->add([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
        ]);

        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age='.self::ONE_YEAR_IN_SECONDS.'; includeSubDomains');
        }

        return $response;
    }
}
