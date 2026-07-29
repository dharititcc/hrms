<?php

namespace App\Policies;

use App\Enums\Action;
use App\Enums\Module;
use App\Models\Employee;
use App\Models\User;
use App\Policies\Concerns\AuthorizesWorkspaceAccess;

class EmployeePolicy
{
    use AuthorizesWorkspaceAccess;

    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Module::Employees, Action::View);
    }

    public function view(User $user, Employee $employee): bool
    {
        return $this->allowsInWorkspace($user, $employee->owner_id, Module::Employees, Action::View);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Module::Employees, Action::Create);
    }

    public function update(User $user, Employee $employee): bool
    {
        return $this->allowsInWorkspace($user, $employee->owner_id, Module::Employees, Action::Edit);
    }

    public function delete(User $user, Employee $employee): bool
    {
        return $this->allowsInWorkspace($user, $employee->owner_id, Module::Employees, Action::Delete);
    }
}
