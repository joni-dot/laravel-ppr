<?php

use App\Http\Middleware\AddContentSecurityPolicy;
use App\Http\Middleware\AddSecurityHeaders;
use App\Http\Middleware\EnsureUserIsActive;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LimitPasswordResetAttempts;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\AuthenticateSession;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append([AddSecurityHeaders::class, AddContentSecurityPolicy::class]);

        // Links in emails are built from the request's host name, so only APP_URL's own host may
        // reach the app. Laravel skips this check locally and in tests.
        $middleware->trustHosts(at: fn (): array => array_filter([
            ($host = parse_url((string) config('app.url'), PHP_URL_HOST)) ? '^'.preg_quote($host).'$' : null,
        ]), subdomains: false);

        // Whoever ends up on the sign-in page (session expired, signed out by a password change
        // elsewhere) may not be the person who used this browser, so the kept pages are forgotten.
        $middleware->redirectGuestsTo(function (): string {
            Inertia::clearHistory();

            return route('login');
        });

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            // Signs out every other session once the password changes.
            AuthenticateSession::class,
            EnsureUserIsActive::class,
            LimitPasswordResetAttempts::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // A redirect after a PUT, PATCH or DELETE must be 303, or the browser repeats the same
        // method. HandleInertiaRequests does this for normal responses, but an exception thrown
        // by middleware that runs before it (AuthenticateSession) would get a plain 302.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request): Response {
            if ($request->header('X-Inertia') && $response->getStatusCode() === 302
                && in_array($request->method(), ['PUT', 'PATCH', 'DELETE'], true)) {
                $response->setStatusCode(303);
            }

            return $response;
        });
    })->create();
