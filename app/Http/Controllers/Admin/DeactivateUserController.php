<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class DeactivateUserController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        $user->deactivated_at = now();
        $user->save();

        Password::broker('invitations')->deleteToken($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was deactivated.', ['name' => $user->name])]);

        return back();
    }
}
