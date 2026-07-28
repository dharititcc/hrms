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
 * KNOWN LIMIT: these are role-level, not row-level. A role holding leave.view
 * sees every leave request in the workspace, not only its own. Restricting
 * people to their own records needs per-query scoping in each module and is
 * not what this class decides — which is why Employee is granted no payroll
 * access at all rather than "their own payslip".
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
                Module::Staff->value => [Action::View, Action::Create, Action::Edit, Action::Delete, Action::Assign, Action::Export],
                Module::Attendance->value => [Action::View, Action::Create, Action::Edit, Action::Export],
                Module::Leave->value => [Action::View, Action::Create, Action::Edit, Action::Approve, Action::Export],
                Module::Payroll->value => [Action::View, Action::Create, Action::Edit, Action::Export],
                Module::Expenses->value => [Action::View, Action::Create, Action::Edit, Action::Approve, Action::Export],
                Module::Tasks->value => self::ALL,
                Module::Meetings->value => self::ALL,
                Module::Projects->value => self::ALL,
                Module::Recruitment->value => [Action::View, Action::Create, Action::Edit, Action::Delete],
                Module::Performance->value => [Action::View, Action::Create, Action::Edit],
                Module::Assets->value => [Action::View, Action::Create, Action::Edit, Action::Delete],
                Module::Announcements->value => [Action::View, Action::Create, Action::Edit, Action::Delete],
                Module::Reports->value => [Action::View, Action::Export],
                Module::Attachments->value => [Action::View, Action::Upload, Action::Delete],
                Module::Activity->value => [Action::View],
            ],

            WorkspaceRole::Employee->value => [
                Module::Staff->value => [Action::View],
                // Clocking in and requesting leave are creates against oneself.
                Module::Attendance->value => [Action::View, Action::Create],
                Module::Leave->value => [Action::View, Action::Create],
                // Deliberately no payroll: without row-level scoping, any read
                // would expose every salary in the workspace.
                Module::Expenses->value => [Action::View, Action::Create],
                Module::Tasks->value => [Action::View, Action::Create, Action::Edit, Action::Comment, Action::Upload],
                Module::Meetings->value => [Action::View, Action::Create, Action::Edit, Action::Comment, Action::Upload],
                Module::Projects->value => [Action::View],
                Module::Recruitment->value => [Action::View],
                Module::Performance->value => [Action::View],
                Module::Assets->value => [Action::View],
                Module::Announcements->value => [Action::View],
                Module::Attachments->value => [Action::View, Action::Upload],
                Module::Activity->value => [Action::View],
            ],

            // An external collaborator: read the work they are involved in and
            // take part in discussion, nothing else.
            WorkspaceRole::Client->value => [
                Module::Tasks->value => [Action::View, Action::Comment],
                Module::Meetings->value => [Action::View, Action::Comment],
                Module::Projects->value => [Action::View],
                Module::Announcements->value => [Action::View],
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
