<?php

namespace App\Services;

use App\Models\Employee;
use App\Models\User;
use App\Notifications\EmployeeInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a directory-only employee record into a login account.
 *
 * Reuses the existing password broker: the invitee receives a set-password link
 * rather than a generated password, so no credential is ever transmitted.
 */
class EmployeeInvitationService
{
    public function invite(Employee $employee): User
    {
        if ($employee->user_id !== null) {
            throw ValidationException::withMessages(['employee' => 'This employee already has an account.']);
        }

        $existing = User::query()->where('email', $employee->email)->first();

        // The staff.user_id column is unique, so an account can belong to one workspace only.
        if ($existing !== null && $existing->employeeProfile()->exists()) {
            throw ValidationException::withMessages(['employee' => 'That email already belongs to another workspace.']);
        }

        $user = DB::transaction(function () use ($employee, $existing): User {
            $user = $existing ?? User::create([
                'name' => $employee->name,
                'email' => $employee->email,
                // Unusable placeholder; replaced when the invitee sets their own.
                'password' => Str::password(32),
            ]);

            $employee->user_id = $user->id;
            $employee->save();

            return $user;
        });

        $user->notify(new EmployeeInvitationNotification(
            token: Password::createToken($user),
            workspaceName: $employee->owner?->name ?? 'your workspace',
        ));

        return $user;
    }

    /** Unlinks the login account, returning the employee to directory-only. */
    public function revoke(Employee $employee): void
    {
        DB::transaction(function () use ($employee): void {
            $employee->user?->tokens()->delete();
            $employee->user_id = null;
            $employee->save();
        });
    }
}
