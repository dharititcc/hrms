<?php

namespace App\Policies;

use App\Models\Staff;
use App\Models\User;

class StaffPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Staff $staff): bool
    {
        return $staff->owner_id === $user->id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Staff $staff): bool
    {
        return $staff->owner_id === $user->id;
    }

    public function delete(User $user, Staff $staff): bool
    {
        return $staff->owner_id === $user->id;
    }
}
