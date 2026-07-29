<?php

namespace App\Services;

use App\Enums\Action;
use App\Enums\MeetingStatus;
use App\Enums\Module;
use App\Enums\TaskStatus;
use App\Models\AuditLog;
use App\Models\Meeting;
use App\Models\Project;
use App\Models\Staff;
use App\Models\Task;
use App\Models\User;

/**
 * Workspace-wide counts for the dashboard.
 *
 * Every query is scoped to the caller's workspace, and "mine" figures use the
 * caller's own user id rather than the workspace owner, so an invited staff
 * member sees their own workload.
 */
class DashboardService
{
    public function summary(User $user): array
    {
        $ownerId = $user->workspaceOwnerId();
        $today = now()->toDateString();

        // Each section is omitted rather than zeroed when the caller lacks
        // permission, so a Client is never shown staff or project figures.
        return array_filter([
            'tasks' => $user->hasPermission(Module::Tasks, Action::View) ? $this->taskStats($ownerId, $user, $today) : null,
            'meetings' => $user->hasPermission(Module::Meetings, Action::View) ? $this->meetingStats($ownerId, $user) : null,
            'people' => $user->hasPermission(Module::Staff, Action::View) ? $this->peopleStats($ownerId) : null,
            'projects' => $user->hasPermission(Module::Projects, Action::View) ? $this->projectStats($ownerId) : null,
            'recent_activity' => $user->hasPermission(Module::Activity, Action::View) ? $this->recentActivity($ownerId) : null,
        ], fn ($section) => $section !== null);
    }

    private function taskStats(int $ownerId, User $user, string $today): array
    {
        $userId = $user->id;

        // Counts must respect row scoping, or a Client would learn how many
        // tasks exist that they cannot open.
        $base = fn () => Task::query()
            ->where('owner_id', $ownerId)
            ->active()
            ->when(
                ! $user->hasPermission(Module::Tasks, Action::ViewAll),
                fn ($query) => $query->where(function ($query) use ($userId): void {
                    $query->where('is_public', true)
                        ->orWhere('created_by', $userId)
                        ->orWhereHas('assignees', fn ($q) => $q->where('users.id', $userId))
                        ->orWhereHas('followers', fn ($q) => $q->where('users.id', $userId));
                }),
            );

        return [
            'total' => $base()->count(),
            'pending' => $base()->where('status', TaskStatus::Pending)->count(),
            'in_progress' => $base()->where('status', TaskStatus::InProgress)->count(),
            'review' => $base()->where('status', TaskStatus::Review)->count(),
            'completed' => $base()->where('status', TaskStatus::Completed)->count(),
            'overdue' => $base()->overdue()->count(),
            'due_today' => $base()
                ->whereDate('due_date', $today)
                ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
                ->count(),
            'mine' => $base()
                ->whereHas('assignees', fn ($query) => $query->where('users.id', $userId))
                ->whereNotIn('status', [TaskStatus::Completed->value, TaskStatus::Cancelled->value])
                ->count(),
            'unassigned' => $base()->whereDoesntHave('assignees')->count(),
        ];
    }

    private function meetingStats(int $ownerId, User $user): array
    {
        $userId = $user->id;

        $base = fn () => Meeting::query()
            ->where('owner_id', $ownerId)
            ->when(
                ! $user->hasPermission(Module::Meetings, Action::ViewAll),
                fn ($query) => $query->where(function ($query) use ($userId): void {
                    $query->whereHas('participants', fn ($q) => $q->where('user_id', $userId))
                        ->orWhere('host_id', $userId)
                        ->orWhere('organizer_id', $userId);
                }),
            );

        return [
            'today' => $base()
                ->whereDate('starts_at', now()->toDateString())
                ->whereNotIn('status', [MeetingStatus::Cancelled->value, MeetingStatus::Rescheduled->value])
                ->count(),
            'this_week' => $base()->between(now(), now()->addWeek())->count(),
            'upcoming' => $base()->upcoming()->count(),
            // Invitations still waiting on this person specifically.
            'awaiting_my_reply' => $base()
                ->upcoming()
                ->whereHas('participants', fn ($query) => $query->where('user_id', $userId)->where('rsvp', 'pending'))
                ->count(),
        ];
    }

    private function peopleStats(int $ownerId): array
    {
        $staff = Staff::query()->where('owner_id', $ownerId);

        return [
            'staff' => (clone $staff)->count(),
            'active' => (clone $staff)->where('status', 'active')->count(),
            // Staff who can sign in, and so can be assigned work.
            'with_accounts' => (clone $staff)->whereNotNull('user_id')->count(),
        ];
    }

    private function projectStats(int $ownerId): array
    {
        $projects = Project::query()->where('owner_id', $ownerId);

        return [
            'total' => (clone $projects)->count(),
            'active' => (clone $projects)->where('status', 'active')->count(),
        ];
    }

    private function recentActivity(int $ownerId): array
    {
        return AuditLog::query()
            ->where('owner_id', $ownerId)
            ->with('user')
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (AuditLog $log) => [
                'id' => $log->id,
                'action' => $log->action,
                'entity' => $log->entity,
                'entity_id' => $log->entity_id,
                'user_name' => $log->user?->name,
                'created_at' => $log->created_at?->toISOString(),
            ])
            ->all();
    }
}
