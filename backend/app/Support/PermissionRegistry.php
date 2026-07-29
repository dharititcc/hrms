<?php

namespace App\Support;

use App\Enums\Action;
use App\Enums\Module;
use App\Enums\WorkspaceRole;

/**
 * The permission matrix: which role may perform which action on which module.
 *
 * Permissions are resource-scoped ("payroll.view", "tasks.create"), so a grant
 * never leaks across modules. Defined in code rather than the database because
 * the set is fixed by the product; that also means a role cannot be
 * misconfigured at runtime into locking everybody out.
 *
 * Two levels of visibility. On the personal modules — attendance, leave,
 * payroll, expenses — "view" means your own records and "view-all" means
 * everyone's. Queries enforce that split via App\Support\RecordScope, so an
 * Employee can read their own payslip without seeing the payroll.
 */
final class PermissionRegistry
{
    private const ALL = '*';

    /**
     * role => module => actions (or '*' for every action on that module).
     *
     * @return array<string, array<string, list<Action>|string>>
     */
    private static function matrix(): array
    {
        return [
            // Admin holds everything, including modules added later.
            WorkspaceRole::Admin->value => self::ALL,

            WorkspaceRole::Manager->value => [
                Module::Staff->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Delete, Action::Assign, Action::Export],
                Module::Attendance->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Export],
                Module::Leave->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Approve, Action::Export],
                Module::Payroll->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Export],
                Module::Expenses->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Approve, Action::Export],
                Module::Tasks->value => self::ALL,
                Module::Meetings->value => self::ALL,
                Module::Projects->value => self::ALL,
                Module::Recruitment->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Delete],
                Module::Performance->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit],
                Module::Assets->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Delete],
                Module::Announcements->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Delete],
                Module::Reports->value => [Action::View, Action::ViewAll, Action::Export],
                Module::Attachments->value => [Action::View, Action::ViewAll, Action::Upload, Action::Delete],
                Module::Activity->value => [Action::View, Action::ViewAll],
            ],

            WorkspaceRole::Employee->value => [
                // The staff directory is shared; personal modules are not.
                Module::Staff->value => [Action::View, Action::ViewAll],
                // View without view-all: their own attendance, leave, payslips
                // and expense claims, never a colleague's.
                Module::Attendance->value => [Action::View, Action::Create],
                Module::Leave->value => [Action::View, Action::Create],
                Module::Payroll->value => [Action::View],
                Module::Expenses->value => [Action::View, Action::Create],
                // Collaborative work is visible across the workspace.
                Module::Tasks->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Comment, Action::Upload],
                Module::Meetings->value => [Action::View, Action::ViewAll, Action::Create, Action::Edit, Action::Comment, Action::Upload],
                Module::Projects->value => [Action::View, Action::ViewAll],
                Module::Recruitment->value => [Action::View, Action::ViewAll],
                Module::Performance->value => [Action::View],
                Module::Assets->value => [Action::View, Action::ViewAll],
                Module::Announcements->value => [Action::View, Action::ViewAll],
                Module::Attachments->value => [Action::View, Action::ViewAll, Action::Upload],
                Module::Activity->value => [Action::View, Action::ViewAll],
            ],

            // An external collaborator: read the work they are involved in and
            // take part in discussion, nothing else. No view-all anywhere, so
            // they see only tasks and meetings they belong to.
            WorkspaceRole::Client->value => [
                Module::Tasks->value => [Action::View, Action::Comment],
                Module::Meetings->value => [Action::View, Action::Comment],
                Module::Projects->value => [Action::View, Action::ViewAll],
                Module::Announcements->value => [Action::View, Action::ViewAll],
                Module::Attachments->value => [Action::View],
            ],
        ];
    }

    public static function allows(WorkspaceRole $role, Module $module, Action $action): bool
    {
        $forRole = self::matrix()[$role->value] ?? [];

        if ($forRole === self::ALL) {
            return true;
        }

        $forModule = $forRole[$module->value] ?? [];

        if ($forModule === self::ALL) {
            return true;
        }

        return in_array($action, $forModule, strict: true);
    }

    /**
     * Flat "module.action" list for a role, for the permissions endpoint and
     * for driving what the UI offers.
     *
     * @return list<string>
     */
    public static function permissionsFor(WorkspaceRole $role): array
    {
        $granted = [];

        foreach (Module::cases() as $module) {
            foreach (Action::cases() as $action) {
                if (self::allows($role, $module, $action)) {
                    $granted[] = "{$module->value}.{$action->value}";
                }
            }
        }

        return $granted;
    }

    /** Every permission the system defines, used to register gates. */
    public static function all(): array
    {
        $permissions = [];

        foreach (Module::cases() as $module) {
            foreach (Action::cases() as $action) {
                $permissions[] = [$module, $action];
            }
        }

        return $permissions;
    }
}
