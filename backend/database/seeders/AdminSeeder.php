<?php

namespace Database\Seeders;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Support\PermissionRegistry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the default super administrator.
 *
 * This application derives roles rather than storing them: a user with no
 * staff record is the workspace owner and therefore holds every permission in
 * App\Support\PermissionRegistry. There is no roles table to write to and no
 * role to attach, so seeding the owner is what "super admin" means here.
 *
 * Idempotent: re-running matches on email and never overwrites an existing
 * account's password, so a changed password is not silently reset.
 */
class AdminSeeder extends Seeder
{
    private const DEFAULT_PASSWORD = 'password';

    public function run(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $password = env('ADMIN_PASSWORD', self::DEFAULT_PASSWORD);

        // A publicly known password on a live system is not a seeding concern
        // worth taking a chance on.
        if (app()->isProduction() && $password === self::DEFAULT_PASSWORD) {
            $this->command?->error('Refusing to seed the default admin password in production. Set ADMIN_PASSWORD.');

            return;
        }

        $existing = User::query()->where('email', $email)->first();

        if ($existing !== null) {
            $this->command?->info("Super Admin already exists: {$email} (no changes made).");
            $this->report($existing);

            return;
        }

        $admin = User::create([
            'name' => env('ADMIN_NAME', 'Super Admin'),
            'email' => $email,
            'password' => Hash::make($password),
            // Verified so the account is usable without a mail round trip.
            'email_verified_at' => now(),
        ]);

        $this->command?->info("Super Admin created: {$email}");
        $this->report($admin);
    }

    private function report(User $admin): void
    {
        $role = $admin->workspaceRole();
        $permissions = count(PermissionRegistry::permissionsFor($role));

        $this->command?->line("  Role: {$role->value}".($role === WorkspaceRole::Admin ? ' (workspace owner)' : ''));
        $this->command?->line("  Permissions: {$permissions}");

        if ($role !== WorkspaceRole::Admin) {
            // Would mean the account is attached to somebody else's workspace.
            $this->command?->warn('  This account is not a workspace owner, so it does not hold every permission.');
        }
    }
}
