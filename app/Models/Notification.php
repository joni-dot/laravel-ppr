<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Carbon;

/**
 * A stored in-app notification. The layout of type, project_id and data is described in
 * App\Notifications\PackageNotification.
 *
 * @property string $id
 * @property string $type
 * @property string $notifiable_type
 * @property int $notifiable_id
 * @property int $project_id
 * @property array{package_id: int, package_number: string, package_title: string, params: array<string, mixed>} $data
 * @property Carbon|null $read_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Notification extends DatabaseNotification
{
    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * A person's own notifications, leaving out those of projects they can no longer see.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query
            ->where('notifiable_type', $user->getMorphClass())
            ->where('notifiable_id', $user->id)
            ->whereHas('project', fn (Builder $project) => $project->visibleTo($user));
    }

    /**
     * Found only among the signed-in person's visible notifications, so anyone else's id answers "not found".
     *
     * @param  mixed  $value
     * @param  string|null  $field
     */
    public function resolveRouteBinding($value, $field = null): self
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 404);

        return self::query()->visibleTo($user)->whereKey($value)->firstOrFail();
    }
}
