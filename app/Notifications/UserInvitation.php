<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class UserInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly string $token,
        private readonly int $expiresInDays,
    ) {}

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
            ->line(__('This link expires in :days days.', ['days' => $this->expiresInDays]));
    }
}
