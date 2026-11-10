<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Sent to the old address, so a person whose email was changed by someone else finds out.
 */
class EmailAddressChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $newEmail,
    ) {}

    /**
     * @return list<string>
     */
    public function via(AnonymousNotifiable $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(AnonymousNotifiable $notifiable): MailMessage
    {
        $appName = config('app.name');

        return (new MailMessage)
            ->subject(__('Your :app email address was changed', ['app' => $appName]))
            ->line(__('The email address of your :app account was changed to :email.', ['app' => $appName, 'email' => $this->newEmail]))
            ->line(__('If you didn\'t make this change, contact your administrator right away.'));
    }
}
