<?php

namespace App\Models;

use App\Enums\ProjectRole;
use App\Enums\Role;
use App\Enums\UserStatus;
use Carbon\CarbonImmutable;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Role $role
 * @property Carbon|null $email_verified_at
 * @property string|null $password
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property CarbonImmutable|null $deactivated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, TwoFactorAuthenticatable;

    /**
     * New users get the least powerful role until an admin decides otherwise.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'role' => Role::Member->value,
    ];

    /**
     * Project roles already looked up during this request, so several permission
     * checks on one page cost one query.
     *
     * @var array<int, ProjectRole|null>
     */
    private array $projectRoles = [];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'two_factor_confirmed_at' => 'datetime',
            'role' => Role::class,
            'deactivated_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === Role::Admin;
    }

    /**
     * @return HasMany<ProjectMember, $this>
     */
    public function projectMemberships(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    /**
     * The person's role in the project, or null when they are not a member.
     */
    public function roleIn(Project $project): ?ProjectRole
    {
        if (! array_key_exists($project->id, $this->projectRoles)) {
            $this->projectRoles[$project->id] = $this->projectMemberships()
                ->where('project_id', $project->id)
                ->value('role');
        }

        return $this->projectRoles[$project->id];
    }

    /**
     * People who can still sign in or accept their invitation.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function notDeactivated(Builder $query): void
    {
        $query->whereNull('deactivated_at');
    }

    public function isDeactivated(): bool
    {
        return $this->deactivated_at !== null;
    }

    public function status(): UserStatus
    {
        return match (true) {
            $this->isDeactivated() => UserStatus::Deactivated,
            $this->password === null => UserStatus::Invited,
            default => UserStatus::Active,
        };
    }
}
