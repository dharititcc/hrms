<?php

namespace App\Support;

use App\Models\Announcement;
use App\Models\Asset;
use App\Models\Candidate;
use App\Models\Employee;
use App\Models\EmployeePayrollProfile;
use App\Models\Expense;
use App\Models\JobOpening;
use App\Models\LeaveRequest;
use App\Models\Meeting;
use App\Models\PayrollRun;
use App\Models\Project;
use App\Models\SalarySlip;
use App\Models\Task;
use App\Models\TaskComment;
use App\Models\User;

/**
 * Single source of truth for polymorphic type aliases.
 *
 * Two distinct lists, deliberately kept apart:
 *
 *  - map()      workspace-scoped records. Every entry has an owner_id, so they
 *               can be resolved with a tenancy check and may carry attachments
 *               and activity. This is what clients are allowed to name.
 *  - morphMap() everything Eloquent must be able to resolve, which additionally
 *               includes User for the polymorphic notifications table. User is
 *               NOT attachable — it has no owner_id.
 */
final class WorkspaceRecords
{
    /** @return array<string, class-string> */
    public static function map(): array
    {
        return [
            'task' => Task::class,
            'meeting' => Meeting::class,
            'project' => Project::class,
            'employee' => Employee::class,
            'expense' => Expense::class,
            'payroll_run' => PayrollRun::class,
            'salary_slip' => SalarySlip::class,
            'asset' => Asset::class,
            'job_opening' => JobOpening::class,
            'candidate' => Candidate::class,
            'announcement' => Announcement::class,
            'leave_request' => LeaveRequest::class,
        ];
    }

    /** @return list<string> */
    public static function aliases(): array
    {
        return array_keys(self::map());
    }

    public static function supports(string $alias): bool
    {
        return array_key_exists($alias, self::map());
    }

    /** @return array<string, class-string> */
    public static function morphMap(): array
    {
        return [
            ...self::map(),
            // Not workspace-resolvable on their own, but Eloquent must map them:
            // User backs the polymorphic notifications table, and TaskComment
            // carries attachments while being scoped through its parent task.
            'user' => User::class,
            'task_comment' => TaskComment::class,
            // Logs activity, so it needs an alias — but stays out of map():
            // bank details are not somewhere to attach files, and nothing a
            // client sends should be able to name them.
            'employee_payroll_profile' => EmployeePayrollProfile::class,
        ];
    }
}
