<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forgets the pages kept in the browser's history, so the Back button can't show them to the
 * next person at the same computer.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        Inertia::clearHistory();

        return redirect(Fortify::redirects('logout', '/'));
    }
}
