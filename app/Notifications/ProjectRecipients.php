<?php

namespace App\Notifications;

use App\Enums\ProjectRole;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * The project's active members, read with one query, for jobs that notify many people about many
 * packages. A package's owner or buyer who has been deactivated or has left the project is not among them.
 */
final readonly class ProjectRecipients
{
    /**
     * @param  Collection<int, User>  $people  per user id
     * @param  list<int>  $managerIds
     */
    private function __construct(
        private Collection $people,
        private array $managerIds,
    ) {}

    public static function of(Project $project): self
    {
        $people = User::query()
            ->notDeactivated()
            ->join('project_members', 'project_members.user_id', '=', 'users.id')
            ->where('project_members.project_id', $project->id)
            ->get(['users.*', 'project_members.role as project_role'])
            ->keyBy('id');

        $managerIds = $people
            ->filter(fn (User $person) => $person->getAttribute('project_role') === ProjectRole::ProjectManager->value)
            ->keys()
            ->all();

        return new self($people, array_values($managerIds));
    }

    /**
     * The given people who are active members, each once. Unknown and empty ids are left out.
     *
     * @param  iterable<int|null>  $userIds
     * @return list<User>
     */
    public function among(iterable $userIds): array
    {
        $found = [];

        foreach ($userIds as $userId) {
            if ($userId !== null && $this->people->has($userId)) {
                $found[$userId] = $this->people->get($userId);
            }
        }

        return array_values($found);
    }

    /**
     * @return list<int>
     */
    public function managerIds(): array
    {
        return $this->managerIds;
    }
}
