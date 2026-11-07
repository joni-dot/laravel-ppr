<?php

namespace App\Notifications;

use App\Models\Package;
use App\Models\Project;
use App\Models\User;
use App\Notifications\Channels\ProjectDatabaseChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * An in-app notification about one package. Every kind is a subclass and is stored the same way,
 * so the notifications page shows any kind without knowing its class:
 *
 * - notifications.type: the kind, such as "package_assigned" (kind()). Never a class name, so a class can be renamed.
 * - notifications.project_id: the package's project. People only see notifications of projects they can see.
 * - notifications.data: {"package_id": 12, "package_number": "P100-ELE-0007", "package_title": "Main transformer",
 *   "params": {...}}, where params are the kind's own values (params()). Number and title are kept as they were
 *   when the notification was sent, so the page needs no query per notification.
 *
 * To add a kind: a subclass with kind() and params(), and a line for the kind in
 * resources/js/components/notifications/notificationMessages.ts.
 */
abstract class PackageNotification extends Notification implements ShouldQueue
{
    use Queueable;

    // Protected, not private: a queued notification is stored with only the properties its own class can see.
    protected readonly int $projectId;

    protected readonly int $packageId;

    protected readonly string $packageNumber;

    protected readonly string $packageTitle;

    public function __construct(Package $package)
    {
        $this->projectId = $package->project_id;
        $this->packageId = $package->id;
        $this->packageNumber = $package->number;
        $this->packageTitle = $package->title;
    }

    /**
     * The kind's name, lower case with underscores. The page picks its message by it.
     */
    abstract public static function kind(): string;

    /**
     * The kind's own values for its message. Only plain values and lists of texts, because they are stored as JSON.
     *
     * @return array<string, string|int|bool|list<string>|null>
     */
    abstract protected function params(): array;

    /**
     * @return list<class-string>
     */
    public function via(User $notifiable): array
    {
        return [ProjectDatabaseChannel::class];
    }

    /**
     * Asked again when the queued notification is sent, because the person may have been deactivated
     * or left the project in the meantime. The project rule is the same one the notifications page uses.
     */
    public function shouldSend(User $notifiable, string $channel): bool
    {
        return ! $notifiable->isDeactivated()
            && Project::query()->visibleTo($notifiable)->whereKey($this->projectId)->exists();
    }

    public function databaseType(User $notifiable): string
    {
        return static::kind();
    }

    public function projectId(): int
    {
        return $this->projectId;
    }

    /**
     * @return array{package_id: int, package_number: string, package_title: string, params: array<string, string|int|bool|list<string>|null>}
     */
    final public function toDatabase(User $notifiable): array
    {
        return [
            'package_id' => $this->packageId,
            'package_number' => $this->packageNumber,
            'package_title' => $this->packageTitle,
            'params' => $this->params(),
        ];
    }
}
