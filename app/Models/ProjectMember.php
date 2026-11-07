<?php

namespace App\Models;

use App\Enums\ProjectRole;
use Database\Factories\ProjectMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A person's membership in one project, with their role there.
 *
 * @property int $id
 * @property int $project_id
 * @property int $user_id
 * @property ProjectRole $role
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class ProjectMember extends Model
{
    /** @use HasFactory<ProjectMemberFactory> */
    use HasFactory;

    protected $fillable = ['role'];

    protected function casts(): array
    {
        return [
            'role' => ProjectRole::class,
        ];
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
