<?php

namespace App\Notifications\Channels;

use App\Notifications\ProjectNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;
use LogicException;

/**
 * Laravel's database channel, plus the notification's project in its own column.
 */
class ProjectDatabaseChannel extends DatabaseChannel
{
    /**
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        if (! $notification instanceof ProjectNotification) {
            throw new LogicException('Only project notifications are stored with a project.');
        }

        return [
            ...parent::buildPayload($notifiable, $notification),
            'project_id' => $notification->projectId(),
        ];
    }
}
