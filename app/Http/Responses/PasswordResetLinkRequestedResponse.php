<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gives the same answer whether or not the address has an account, so the form can't be used
 * to find out who uses the app.
 */
class PasswordResetLinkRequestedResponse implements FailedPasswordResetLinkRequestResponse, SuccessfulPasswordResetLinkRequestResponse
{
    /**
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        $message = __('If an account uses this email address, we have emailed it a password reset link.');

        return $request->wantsJson()
            ? new JsonResponse(['message' => $message])
            : back()->with('status', $message);
    }
}
