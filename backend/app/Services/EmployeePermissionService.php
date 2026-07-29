<?php

namespace App\Services;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Employee;
use App\Models\EmployeePermissionOverride;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Per-employee departures from what their role grants.
 *
 * Callers send the complete set of permissions the employee should end up
 * with. The difference against their role is what gets stored, so somebody
 * whose access matches their role leaves no rows behind and the table stays a
 * list of exceptions.
 */
class EmployeePermissionService
{
    /**
     * @param  list<string>  $desired  every "module.action" the employee should hold
     */
    public function sync(Employee $employee, User $actor, array $desired): void
    {
        $desired = array_values(array_unique(array_filter($desired, $this->isReal(...))));

        $this->assertActorHolds($actor, $desired, $employee);

        $fromRole = PermissionRegistry::permissionsFor($employee->workspaceRole());

        $added = array_diff($desired, $fromRole);
        $removed = array_diff($fromRole, $desired);

        DB::transaction(function () use ($employee, $actor, $added, $removed): void {
            // Replaced wholesale: a permission that is neither added nor
            // removed is back to following the role, and must leave no row.
            EmployeePermissionOverride::where('staff_id', $employee->id)->delete();

            foreach ([[$added, true], [$removed, false]] as [$permissions, $granted]) {
                foreach ($permissions as $permission) {
                    EmployeePermissionOverride::create([
                        'owner_id' => $employee->owner_id,
                        'staff_id' => $employee->id,
                        'permission' => $permission,
                        'granted' => $granted,
                        'granted_by' => $actor->id,
                    ]);
                }
            }
        });
    }

    /** What this employee may actually do, role adjusted by their overrides. */
    public function effectiveFor(Employee $employee): array
    {
        $granted = PermissionRegistry::permissionsFor($employee->workspaceRole());

        $overrides = EmployeePermissionOverride::query()
            ->where('staff_id', $employee->id)
            ->pluck('granted', 'permission');

        $granted = array_diff($granted, $overrides->reject(fn ($on) => $on)->keys()->all());

        return array_values(array_unique([...$granted, ...$overrides->filter(fn ($on) => $on)->keys()->all()]));
    }

    /**
     * Nobody may hand out access they do not have themselves.
     *
     * Without this, anyone able to edit employees could grant themselves
     * payroll.approve through a colleague's record, or simply give it to
     * somebody who would then act on their behalf. Granting is bounded by the
     * granter, which is a separate question from whether a role permits it —
     * the point of these overrides is that it need not.
     *
     * @param  list<string>  $desired
     */
    private function assertActorHolds(User $actor, array $desired, Employee $employee): void
    {
        $current = $this->effectiveFor($employee);
        // Only what is actually changing has to be justified; leaving an
        // existing permission alone is not a grant.
        $changing = array_merge(array_diff($desired, $current), array_diff($current, $desired));

        $beyond = array_diff($changing, $actor->permissions());

        if ($beyond !== []) {
            throw ValidationException::withMessages([
                'permissions' => 'You cannot change access you do not hold yourself: '.implode(', ', $beyond).'.',
            ]);
        }
    }

    /** Guards against a typo becoming a permission nothing will ever check. */
    private function isReal(mixed $permission): bool
    {
        if (! is_string($permission) || ! str_contains($permission, '.')) {
            return false;
        }

        [$module, $action] = explode('.', $permission, 2);
        $module = Module::tryFrom($module);
        $action = Action::tryFrom($action);

        return $module !== null
            && $action !== null
            && in_array($action, PermissionRegistry::actionsFor($module), strict: true);
    }
}
