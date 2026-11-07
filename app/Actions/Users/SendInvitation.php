<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Notifications\UserInvitation;
use Illuminate\Support\Facades\Password;

class SendInvitation
{
    /**
     * Email the user a single-use link for setting their password.
     * A new invitation replaces any earlier one, so old links stop working.
     */
    public function handle(User $user): void
    {
        $token = Password::broker('invitations')->createToken($user);

        $user->notify(new UserInvitation($token));
    }
}
