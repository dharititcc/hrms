<?php

namespace App\Services;

use App\Enums\Action;
use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Enums\MeetingStatus;
use App\Enums\Module;
use App\Enums\PayrollRunStatus;
use App\Enums\TaskStatus;
use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\LeaveRequest;
use App\Models\LeaveType;
use App\Models\Meeting;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\SalarySlip;
use App\Models\Employee;
use App\Models\Task;
use App\Models\User;

/**
 * Workspace-wide counts for the dashboard.
 *
 * Every query is scoped to the caller's workspace, and "mine" figures use the
 * caller's own user id rather than the workspace owner, so an invited employees
 * member sees their own workload.
 */
class DashboardService
{
    public function summary(User $user): array
    {
        $ownerId = $user->workspaceOwnerId();
        $today = now()->toDateString();

        // Each section is omitted rather than zeroed when the caller lacks
        // permission, so a Client is never shown employee or project figures.
        return array_filter([
            'tasks' => $user->hasPermission(Module::Tasks, Action::View) ? $this->taskStats($ownerId, $user, $today) : null,
            'meetings' => $user->hasPermission(Module::Meetings, Action::View) ? $this->meetingStats($ownerId, $user) : null,
            'attendance' => $user->hasPermission(Module::Attendance, Action::View) ? $this->attendanceStats($ownerId, $user, $today) : null,
            'leave' => $user->hasPermission(Module::Leave, Action::View) ? $this->leaveStats($ownerId, $user) : null,
            'payroll' => $user->hasPermission(Module::Payroll, Action::View) ? $this->payrollStats($ownerId, $user) : null,
            'people' => $user->hasPermission(Module::Employees, Action::View) ? $this->peopleStats($ownerId) : null,
            'projects' => $user->hasPermission(Module::Projects, Action::View) ? $this->projectStats($ownerId) : null,
            'recent_activity' => $user->hasPermission(Module::Activity, Action::View) ? $this->recentActivity($ownerId) : null,
        ], fn ($section) => $section !== null);
    }

    /**
     * Whether the caller has checked in, and — for anyone who can see the whole
     * team — how the day looks across it.
     *
     * The workspace owner has no employee record, so "mine" is simply absent for
     * them rather than being faked against somebody else's row.
     */
    private function attendanceStats(int $ownerId, User $user, string $today): array
    {
        $employeeId = $user->employeeId();
        $mine = $employeeId === null ? null : Attendance::query()
            ->where('staff_id', $employeeId)
            ->whereDate('work_date', $today)
            ->first();

        $stats = [
            'checked_in' => $mine?->check_in !== null,
            'checked_out' => $mine?->check_out !== null,
            'my_attendance_id' => $mine?->id,
            'my_status' => $mine?->status?->value,
            'my_check_in' => $mine?->check_in,
        ];

        if (! $user->hasPermission(Module::Attendance, Action::ViewAll)) {
            return $stats;
        }

        $todays = Attendance::query()
            ->where('owner_id', $ownerId)
            ->whereDate('work_date', $today);

        $activeEmployees = Employee::query()->where('owner_id', $ownerId)->where('status', 'active')->count();
        $present = (clone $todays)->whereIn('status', [AttendanceStatus::Present->value, AttendanceStatus::Late->value])->count();

        return [
            ...$stats,
            'active_employees' => $activeEmployees,
            'present_today' => $present,
            'late_today' => (clone $todays)->where('status', AttendanceStatus::Late->value)->count(),
            'on_leave_today' => (clone $todays)->where('status', AttendanceStatus::OnLeave->value)->count(),
            // Nobody has recorded anything for them yet, which is not the same
            // as being marked absent.
            'not_recorded' => max(0, $activeEmployees - (clone $todays)->count()),
            // Not limited to today: an unapproved record from last week is the
            // one more likely to have been forgotten.
            'awaiting_approval' => $user->hasPermission(Module::Attendance, Action::Edit)
                ? Attendance::query()
                    ->where('owner_id', $ownerId)
                    ->where('requires_approval', true)
                    ->whereNull('approved_at')
                    ->count()
                : 0,
        ];
    }

    /**
     * The caller's own leave standing, plus the approval queue for anyone who
     * decides on requests.
     */
    private function leaveStats(int $ownerId, User $user): array
    {
        $employeeId = $user->employeeId();

        $stats = ['balances' => [], 'my_pending' => 0];

        if ($employeeId !== null) {
            $stats['my_pending'] = LeaveRequest::query()
                ->where('staff_id', $employeeId)
                ->where('status', LeaveRequestStatus::Pending)
                ->count();

            $stats['balances'] = $this->leaveBalances($ownerId, $employeeId);
        }

        if ($user->hasPermission(Module::Leave, Action::Approve)) {
            $stats['awaiting_approval'] = LeaveRequest::query()
                ->where('owner_id', $ownerId)
                ->where('status', LeaveRequestStatus::Pending)
                ->count();
        }

        return $stats;
    }

    /**
     * Days taken against each type's annual allowance.
     *
     * Counts approved requests only — a pending one has not been granted — and
     * measures calendar days inclusive of both ends. Weekends and holidays are
     * not deducted, which overstates a long booking; a working-day calendar
     * would be needed to do better.
     *
     * @return list<array<string, mixed>>
     */
    private function leaveBalances(int $ownerId, int $employeeId): array
    {
        $types = LeaveType::query()->where('owner_id', $ownerId)->where('is_active', true)->get();

        if ($types->isEmpty()) {
            return [];
        }

        $yearStart = now()->startOfYear();

        $taken = LeaveRequest::query()
            ->where('staff_id', $employeeId)
            ->where('status', LeaveRequestStatus::Approved)
            ->whereDate('start_date', '>=', $yearStart->toDateString())
            ->get()
            ->groupBy('leave_type_id')
            ->map(fn ($requests) => $requests->sum(
                fn (LeaveRequest $request) => $request->start_date->diffInDays($request->end_date) + 1,
            ));

        return $types->map(function (LeaveType $type) use ($taken) {
            $used = (int) ($taken[$type->id] ?? 0);

            return [
                'id' => $type->id,
                'name' => $type->name,
                'entitlement' => $type->days_per_year,
                'taken' => $used,
                'remaining' => max(0, $type->days_per_year - $used),
            ];
        })->values()->all();
    }

    /**
     * The caller's most recent payslip, and — for whoever runs payroll — what
     * is sitting in the queue.
     */
    private function payrollStats(int $ownerId, User $user): array
    {
        $employeeId = $user->employeeId();

        $latest = $employeeId === null ? null : SalarySlip::query()
            ->where('staff_id', $employeeId)
            ->whereHas('run', fn ($query) => $query->whereIn('status', [
                PayrollRunStatus::Approved->value, PayrollRunStatus::Paid->value,
            ]))
            ->with('run')
            ->latest('id')
            ->first();

        $stats = [
            'latest_slip' => $latest === null ? null : [
                'id' => $latest->id,
                'slip_number' => $latest->slip_number,
                'period' => $latest->run?->title,
                'net_salary' => $latest->net_salary,
                'currency_symbol' => $latest->country->currencySymbol(),
                'status' => $latest->status->value,
            ],
        ];

        if (! $user->hasPermission(Module::Payroll, Action::ViewAll)) {
            return $stats;
        }

        $runs = PayrollRun::query()->where('owner_id', $ownerId);

        return [
            ...$stats,
            'draft_runs' => (clone $runs)->where('status', PayrollRunStatus::Draft)->count(),
            'awaiting_approval' => (clone $runs)->where('status', PayrollRunStatus::PendingApproval)->count(),
            // Approved but not settled: somebody still has to move the money.
            'awaiting_payment' => (clone $runs)->where('status', PayrollRunStatus::Approved)->count(),
        ];
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
        $employees = Employee::query()->where('owner_id', $ownerId);

        return [
            'employees' => (clone $employees)->count(),
            'active' => (clone $employees)->where('status', 'active')->count(),
            // Employee who can sign in, and so can be assigned work.
            'with_accounts' => (clone $employees)->whereNotNull('user_id')->count(),
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
