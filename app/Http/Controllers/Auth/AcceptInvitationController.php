<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\AcceptInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AcceptInvitationController extends Controller
{
    public function show(Request $request, string $token): Response
    {
        $email = $request->string('email')->toString();
        $user = User::firstWhere('email', $email);

        return Inertia::render('auth/AcceptInvitation', [
            'token' => $token,
            'email' => $email,
            'isValid' => $user !== null && Password::broker('invitations')->tokenExists($user, $token),
            'passwordRules' => PasswordRule::defaults()->toPasswordRulesString(),
        ]);
    }

    public function store(AcceptInvitationRequest $request): RedirectResponse
    {
        $status = Password::broker('invitations')->reset(
            $request->safe()->only(['email', 'password', 'password_confirmation', 'token']),
            function (User $user, string $password) {
                $user->password = $password;
                $user->save();
            },
        );

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => __('This invitation link is invalid or has expired. Ask an admin to send a new one.'),
            ]);
        }

        return to_route('login')->with('status', __('Your password is set. You can now log in.'));
    }
}
