<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Builder;
use App\Enums\Action;
use App\Enums\Module;
use App\Enums\StaffRole;
use App\Enums\WorkspaceRole;
use App\Support\PermissionRegistry;
use Laravel\Sanctum\HasApiTokens;
use App\Notifications\VerifyEmailNotification;


#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable,HasApiTokens;

    private ?int $workspaceOwnerIdCache = null;

    private ?WorkspaceRole $workspaceRoleCache = null;

    private ?int $staffIdCache = null;

    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification());
    }

    /** Staff records this user owns (the workspace directory). */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class, 'owner_id');
    }

    /** This user's own staff record, when they were invited into someone's workspace. */
    public function staffProfile(): HasOne
    {
        return $this->hasOne(Staff::class, 'user_id');
    }

    /**
     * The workspace this user operates in. Owners work in their own workspace;
     * invited staff work in the workspace that owns their staff record.
     *
     * All tenant-scoped queries must filter on this rather than the user id.
     *
     * Memoized: policies call this on every authorization check, so without a
     * per-instance cache a single request issues the same query many times.
     */
    public function workspaceOwnerId(): int
    {
        return $this->workspaceOwnerIdCache ??= ($this->staffProfile()->value('owner_id') ?? $this->id);
    }

    /**
     * Staff belonging to this user's workspace. Use instead of staff() whenever
     * the caller means "the directory I can see" — staff() only returns records
     * this user personally owns, which is empty for an invited staff-user.
     */
    public function workspaceStaff(): Builder
    {
        return Staff::query()->where('owner_id', $this->workspaceOwnerId());
    }

    public function workspaceRole(): WorkspaceRole
    {
        if ($this->workspaceRoleCache !== null) {
            return $this->workspaceRoleCache;
        }

        $staffRole = $this->staffProfile()->value('role');

        // value() returns the cast enum when the attribute is cast, but a raw
        // string when it is not, so accept either.
        $role = match (true) {
            $staffRole === null => null,
            $staffRole instanceof StaffRole => $staffRole,
            default => StaffRole::tryFrom((string) $staffRole),
        };

        return $this->workspaceRoleCache = $role === null
            ? WorkspaceRole::Admin
            : WorkspaceRole::fromStaffRole($role);
    }

    /**
     * This user's own staff record id, which identifies "their own" rows in
     * attendance, leave, payroll and expenses. Null for the workspace owner,
     * who has no staff record.
     */
    public function staffId(): ?int
    {
        return $this->staffIdCache ??= $this->staffProfile()->value('id');
    }

    /** Resource-scoped check: may this user perform $action on $module? */
    public function hasPermission(Module $module, Action $action): bool
    {
        return PermissionRegistry::allows($this->workspaceRole(), $module, $action);
    }

    public function isWorkspaceAdmin(): bool
    {
        return $this->workspaceRole() === WorkspaceRole::Admin;
    }

    /** @return list<string> flat "module.action" permissions */
    public function permissions(): array
    {
        return PermissionRegistry::permissionsFor($this->workspaceRole());
    }

    public function isWorkspaceOwner(): bool
    {
        return $this->workspaceOwnerId() === $this->id;
    }

    /** Clears memoized workspace state, e.g. after an invitation links a staff record. */
    public function forgetWorkspaceCache(): void
    {
        $this->workspaceOwnerIdCache = null;
        $this->workspaceRoleCache = null;
    }

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
        ];
    }
}
