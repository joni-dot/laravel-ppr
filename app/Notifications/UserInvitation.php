<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

// Encrypted in the queue because the job holds a working invitation token.
class UserInvitation extends Notification implements ShouldBeEncrypted, ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
    ) {}

    /**
     * How long an invitation link works. The invite dialog shows the same number.
     */
    public static function linkLifetimeInDays(): int
    {
        return intdiv(config('auth.passwords.invitations.expire'), 60 * 24);
    }

    /**
     * @return list<string>
     */
    public function via(User $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject(__('You are invited to :app', ['app' => $appName]))
            ->line(__('An account has been created for you in :app. Set your password to get started.', ['app' => $appName]))
            ->action(__('Set password'), route('invitation.show', [
                'token' => $this->token,
                'email' => $notifiable->email,
            ]))
            ->line(__('This link expires in :days days.', ['days' => self::linkLifetimeInDays()]));
    }
}
