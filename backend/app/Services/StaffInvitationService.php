<?php

namespace App\Services;

use App\Models\Staff;
use App\Models\User;
use App\Notifications\StaffInvitationNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a directory-only staff record into a login account.
 *
 * Reuses the existing password broker: the invitee receives a set-password link
 * rather than a generated password, so no credential is ever transmitted.
 */
class StaffInvitationService
{
    public function invite(Staff $staff): User
    {
        if ($staff->user_id !== null) {
            throw ValidationException::withMessages(['staff' => 'This staff member already has an account.']);
        }

        $existing = User::query()->where('email', $staff->email)->first();

        // staff.user_id is unique, so an account can belong to one workspace only.
        if ($existing !== null && $existing->staffProfile()->exists()) {
            throw ValidationException::withMessages(['staff' => 'That email already belongs to another workspace.']);
        }

        $user = DB::transaction(function () use ($staff, $existing): User {
            $user = $existing ?? User::create([
                'name' => $staff->name,
                'email' => $staff->email,
                // Unusable placeholder; replaced when the invitee sets their own.
                'password' => Str::password(32),
            ]);

            $staff->user_id = $user->id;
            $staff->save();

            return $user;
        });

        $user->notify(new StaffInvitationNotification(
            token: Password::createToken($user),
            workspaceName: $staff->owner?->name ?? 'your workspace',
        ));

        return $user;
    }

    /** Unlinks the login account, returning the staff member to directory-only. */
    public function revoke(Staff $staff): void
    {
        DB::transaction(function () use ($staff): void {
            $staff->user?->tokens()->delete();
            $staff->user_id = null;
            $staff->save();
        });
    }
}
