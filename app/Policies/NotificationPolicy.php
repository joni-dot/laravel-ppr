<?php

namespace App\Policies;

use App\Models\Notification;
use App\Models\User;

/**
 * Notifications are private: only the person they were sent to opens or changes them.
 */
class NotificationPolicy
{
    public function view(User $user, Notification $notification): bool
    {
        return $this->isOwn($user, $notification);
    }

    public function update(User $user, Notification $notification): bool
    {
        return $this->isOwn($user, $notification);
    }

    private function isOwn(User $user, Notification $notification): bool
    {
        return $notification->notifiable_type === $user->getMorphClass() && $notification->notifiable_id === $user->id;
    }
}
