<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\SendInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ResendInvitationController extends Controller
{
    public function __invoke(User $user, SendInvitation $sendInvitation): RedirectResponse
    {
        $sendInvitation->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return back();
    }
}
