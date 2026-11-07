<?php

namespace App\Policies;

use App\Enums\UserStatus;
use App\Models\User;

/**
 * Only admins manage users. Admins can never change or deactivate their own
 * account, which guarantees the system always keeps at least one active admin.
 */
class UserPolicy
{
    public function viewAny(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function create(User $actor): bool
    {
        return $actor->isAdmin();
    }

    public function update(User $actor, User $user): bool
    {
        return $actor->isAdmin() && $actor->isNot($user);
    }

    public function deactivate(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && ! $user->isDeactivated();
    }

    public function reactivate(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $user->isDeactivated();
    }

    public function resendInvitation(User $actor, User $user): bool
    {
        return $this->update($actor, $user) && $user->status() === UserStatus::Invited;
    }
}
