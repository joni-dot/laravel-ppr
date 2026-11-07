<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Tells the browser to run only scripts that come from this app, so injected scripts are refused.
 *
 * Styles may be inline because the UI libraries add <style> tags while running. Styles cannot run code.
 */
class AddContentSecurityPolicy
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Vite::useCspNonce();

        $response = $next($request);

        if ($this->isDebugErrorPage($response)) {
            return $response;
        }

        $response->headers->set('Content-Security-Policy', $this->policy($nonce, $request->isSecure()));

        return $response;
    }

    private function policy(string $nonce, bool $isSecure): string
    {
        $devServer = $this->viteDevServerOrigin();

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", $devServer],
            'style-src' => ["'self'", "'unsafe-inline'", $devServer],
            'img-src' => ["'self'", 'data:', 'blob:', $devServer],
            'font-src' => ["'self'", $devServer],
            'connect-src' => ["'self'", $devServer, $devServer ? Str::replaceStart('http', 'ws', $devServer) : null],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        $policy = collect($directives)
            ->map(fn (array $sources, string $directive) => $directive.' '.implode(' ', array_filter($sources)))
            ->implode('; ');

        return $isSecure ? "{$policy}; upgrade-insecure-requests" : $policy;
    }

    /**
     * While a developer runs "pnpm run dev", pages load scripts and styles from the Vite dev server.
     */
    private function viteDevServerOrigin(): ?string
    {
        if (! Vite::isRunningHot()) {
            return null;
        }

        return rtrim((string) file_get_contents(Vite::hotFile()));
    }

    /**
     * Laravel's detailed error page (only shown in debug mode on a developer's machine) uses inline scripts.
     */
    private function isDebugErrorPage(Response $response): bool
    {
        return config('app.debug') && property_exists($response, 'exception') && $response->exception !== null;
    }
}
