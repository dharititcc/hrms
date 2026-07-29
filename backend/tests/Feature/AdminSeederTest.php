<?php

namespace Tests\Feature;

use App\Enums\WorkspaceRole;
use App\Models\User;
use App\Support\PermissionRegistry;
use Database\Seeders\AdminSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_super_admin_with_a_hashed_password(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        $this->assertSame('Super Admin', $admin->name);
        // Stored as a bcrypt hash, never in the clear.
        $this->assertNotSame('password', $admin->password);
        $this->assertTrue(Hash::check('password', $admin->password));
        $this->assertNotNull($admin->email_verified_at);
    }

    public function test_running_it_twice_creates_no_duplicate(): void
    {
        $this->seed(AdminSeeder::class);
        $this->seed(AdminSeeder::class);

        $this->assertSame(1, User::where('email', 'admin@example.com')->count());
    }

    public function test_it_does_not_reset_a_password_that_was_changed(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();
        $admin->forceFill(['password' => Hash::make('a-better-password')])->save();

        $this->seed(AdminSeeder::class);

        // Re-seeding must not hand the account back to the default password.
        $this->assertTrue(Hash::check('a-better-password', $admin->fresh()->password));
        $this->assertFalse(Hash::check('password', $admin->fresh()->password));
    }

    public function test_the_seeded_admin_holds_every_permission(): void
    {
        $this->seed(AdminSeeder::class);

        $admin = User::where('email', 'admin@example.com')->firstOrFail();

        // No staff record means workspace owner, which resolves to Admin.
        $this->assertTrue($admin->isWorkspaceOwner());
        $this->assertSame(WorkspaceRole::Admin, $admin->workspaceRole());
        $this->assertSame(
            count(PermissionRegistry::permissionsFor(WorkspaceRole::Admin)),
            count($admin->permissions()),
        );
    }
}
