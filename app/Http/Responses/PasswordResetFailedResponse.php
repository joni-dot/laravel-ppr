<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\FailedPasswordResetResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Laravel answers "no such user" for an unknown address and "invalid token" for a real one, so
 * a made-up token would reveal who has an account. Every failed reset gets the same answer.
 */
class PasswordResetFailedResponse implements FailedPasswordResetResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = __('This password reset link is invalid or has expired.');

        if ($request->wantsJson()) {
            throw ValidationException::withMessages(['email' => [$message]]);
        }

        return back()
            ->withInput($request->only('email'))
            ->withErrors(['email' => $message]);
    }
}
