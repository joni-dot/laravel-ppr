<?php

namespace App\Enums;

/**
 * A person's role in one project. The same person can have a different role in another project.
 */
enum ProjectRole: string
{
    case ProjectManager = 'project_manager';
    case Buyer = 'buyer';
    case PackageOwner = 'package_owner';
    case Viewer = 'viewer';

    public function label(): string
    {
        return match ($this) {
            self::ProjectManager => __('Project manager'),
            self::Buyer => __('Buyer'),
            self::PackageOwner => __('Package owner'),
            self::Viewer => __('Viewer'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ProjectManager => __('Runs the project: members, numbering and risk warnings.'),
            self::Buyer => __('Buys and tracks all packages in the project.'),
            self::PackageOwner => __('Keeps the packages assigned to them up to date.'),
            self::Viewer => __('Can look at everything in the project but change nothing.'),
        };
    }

    /**
     * Roles that add packages and edit every package in the project.
     *
     * @return list<self>
     */
    public static function managingPackages(): array
    {
        return [self::ProjectManager, self::Buyer];
    }

    /**
     * Roles a package's owner can have. Viewers change nothing, so they can't own a package.
     *
     * @return list<self>
     */
    public static function owningPackages(): array
    {
        return [self::ProjectManager, self::Buyer, self::PackageOwner];
    }

    /**
     * Roles a package's responsible buyer can have.
     *
     * @return list<self>
     */
    public static function buyingPackages(): array
    {
        return [self::ProjectManager, self::Buyer];
    }

    /**
     * @return list<array{value: string, label: string, description: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role) => ['value' => $role->value, 'label' => $role->label(), 'description' => $role->description()],
            self::cases(),
        );
    }
}
