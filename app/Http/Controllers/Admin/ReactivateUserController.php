<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class ReactivateUserController extends Controller
{
    public function __invoke(User $user): RedirectResponse
    {
        $user->deactivated_at = null;
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was reactivated.', ['name' => $user->name])]);

        return back();
    }
}
