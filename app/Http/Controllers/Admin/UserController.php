<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\SendInvitation;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    private const int USERS_PER_PAGE = 25;

    public function index(Request $request): Response
    {
        $search = $request->string('search')->trim()->limit(100, '')->toString();

        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'password', 'deactivated_at'])
            ->when($search !== '', fn ($query) => $query->whereAny(['name', 'email'], 'ilike', "%{$search}%"))
            ->orderBy('name')
            ->paginate(self::USERS_PER_PAGE)
            ->withQueryString()
            ->through(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'status' => $user->status(),
                'can' => [
                    'update' => $request->user()->can('update', $user),
                    'deactivate' => $request->user()->can('deactivate', $user),
                    'reactivate' => $request->user()->can('reactivate', $user),
                    'resendInvitation' => $request->user()->can('resendInvitation', $user),
                ],
            ]);

        return Inertia::render('admin/users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
            'roles' => Role::options(),
        ]);
    }

    public function store(StoreUserRequest $request, SendInvitation $sendInvitation): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email']));
        $user->role = $request->enum('role', Role::class);
        $user->save();

        $sendInvitation->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Invitation sent to :email.', ['email' => $user->email])]);

        return to_route('admin.users.index');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->name = $request->validated('name');
        $user->role = $request->enum('role', Role::class);
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('User updated.')]);

        return back();
    }
}
