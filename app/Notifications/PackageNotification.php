<?php

namespace App\Notifications;

use App\Models\Package;

/**
 * An in-app notification about one package. Its stored data (see ProjectNotification) also has
 * {"package_id": 12, "package_number": "P100-ELE-0007", "package_title": "Main transformer"}. Number and
 * title are kept as they were when the notification was sent, so the page needs no query per notification,
 * and opening the notification goes to the package.
 */
abstract class PackageNotification extends ProjectNotification
{
    // Protected, not private: a queued notification is stored with only the properties its own class can see.
    protected readonly int $packageId;

    protected readonly string $packageNumber;

    protected readonly string $packageTitle;

    public function __construct(Package $package)
    {
        parent::__construct($package->project_id);
        $this->packageId = $package->id;
        $this->packageNumber = $package->number;
        $this->packageTitle = $package->title;
    }

    /**
     * @return array{package_id: int, package_number: string, package_title: string}
     */
    final protected function about(): array
    {
        return [
            'package_id' => $this->packageId,
            'package_number' => $this->packageNumber,
            'package_title' => $this->packageTitle,
        ];
    }
}
