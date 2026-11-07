<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\CheckProjectRisks;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ReactivateUserController extends Controller
{
    public function __invoke(Request $request, User $user): RedirectResponse
    {
        $user->deactivated_at = null;
        $user->save();
        CheckProjectRisks::dispatchForProjectsOf($user, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __(':name was reactivated.', ['name' => $user->name])]);

        return back();
    }
}
