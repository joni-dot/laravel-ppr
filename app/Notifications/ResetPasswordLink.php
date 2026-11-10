<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;

/**
 * Laravel's reset email with a link built from APP_URL, never from the request's host name.
 * Sent in the background so a slow mail server doesn't slow the answer; encrypted in the queue
 * because the job holds a working reset token. config('auth.timebox_duration') keeps the answer
 * time the same for unknown addresses.
 */
class ResetPasswordLink extends ResetPassword implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    /**
     * @param  User  $notifiable
     */
    protected function resetUrl($notifiable): string
    {
        return rtrim((string) config('app.url'), '/').route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false);
    }
}
