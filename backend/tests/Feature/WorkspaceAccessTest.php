<?php

namespace Tests\Feature;

use App\Enums\Ability;
use App\Enums\WorkspaceRole;
use App\Models\Staff;
use App\Models\User;
use App\Services\StaffInvitationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use App\Notifications\StaffInvitationNotification;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class WorkspaceAccessTest extends TestCase
{
    use RefreshDatabase;

    private function staffFor(User $owner, string $role = 'member', string $email = 'member@example.com'): Staff
    {
        return Staff::create([
            'owner_id' => $owner->id,
            'name' => 'Grace Hopper',
            'email' => $email,
            'role' => $role,
            'status' => 'active',
        ]);
    }

    /** Invites the staff member and returns their new login account. */
    private function invite(Staff $staff): User
    {
        app(StaffInvitationService::class)->invite($staff);

        return $staff->refresh()->user;
    }

    public function test_inviting_staff_creates_a_linked_account_and_notifies_them(): void
    {
        Notification::fake();

        $owner = User::factory()->create();
        $staff = $this->staffFor($owner);
        Sanctum::actingAs($owner);

        $this->postJson("/api/auth/staff/{$staff->id}/invite")->assertCreated();

        $staff->refresh();
        $this->assertNotNull($staff->user_id);
        $this->assertSame('member@example.com', $staff->user->email);

        Notification::assertSentTo($staff->user, StaffInvitationNotification::class);
    }

    public function test_invited_staff_see_the_owners_workspace_not_their_own(): void
    {
        $owner = User::factory()->create();
        $staff = $this->staffFor($owner);
        $this->staffFor($owner, 'manager', 'colleague@example.com');
        $staffUser = $this->invite($staff);

        // The staff-user's own id is not the workspace id; scoping must follow
        // their staff record's owner, or they would see an empty workspace.
        $this->assertNotSame($owner->id, $staffUser->id);
        $this->assertSame($owner->id, $staffUser->workspaceOwnerId());

        Sanctum::actingAs($staffUser);
        $this->getJson('/api/auth/staff')->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_workspace_owner_is_admin_and_staff_roles_map_to_workspace_roles(): void
    {
        $owner = User::factory()->create();
        $this->assertSame(WorkspaceRole::Admin, $owner->workspaceRole());
        $this->assertTrue($owner->isWorkspaceOwner());

        $employee = $this->invite($this->staffFor($owner, 'member', 'employee@example.com'));
        $manager = $this->invite($this->staffFor($owner, 'manager', 'manager@example.com'));
        $client = $this->invite($this->staffFor($owner, 'client', 'client@example.com'));

        $this->assertSame(WorkspaceRole::Employee, $employee->workspaceRole());
        $this->assertSame(WorkspaceRole::Manager, $manager->workspaceRole());
        $this->assertSame(WorkspaceRole::Client, $client->workspaceRole());
        $this->assertFalse($employee->isWorkspaceOwner());
    }

    public function test_role_abilities_are_enforced_by_policies(): void
    {
        $owner = User::factory()->create();
        $employee = $this->invite($this->staffFor($owner, 'member', 'employee@example.com'));
        $client = $this->invite($this->staffFor($owner, 'client', 'client@example.com'));
        $target = $this->staffFor($owner, 'member', 'target@example.com');

        // Employees may create and edit, but not delete.
        $this->assertTrue($employee->hasAbility(Ability::Edit));
        $this->assertFalse($employee->hasAbility(Ability::Delete));

        Sanctum::actingAs($employee);
        $this->deleteJson("/api/auth/staff/{$target->id}")->assertForbidden();

        // Clients are read-only apart from commenting.
        Sanctum::actingAs($client);
        $this->getJson('/api/auth/staff')->assertOk();
        $this->postJson('/api/auth/staff', [
            'name' => 'New person',
            'email' => 'new@example.com',
            'role' => 'member',
            'status' => 'active',
        ])->assertForbidden();
    }

    public function test_manage_all_does_not_cross_workspace_boundaries(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $staffOfB = $this->staffFor($ownerB, 'member', 'b-staff@example.com');

        // Both owners are Admins holding manage-all; that must never let one
        // reach the other's records.
        $this->assertTrue($ownerA->hasAbility(Ability::ManageAll));

        Sanctum::actingAs($ownerA);
        $this->getJson("/api/auth/staff/{$staffOfB->id}")->assertForbidden();
        $this->getJson('/api/auth/staff')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_an_account_cannot_be_invited_into_two_workspaces(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $this->invite($this->staffFor($ownerA, 'member', 'shared@example.com'));
        $duplicate = $this->staffFor($ownerB, 'member', 'shared@example.com');

        Sanctum::actingAs($ownerB);
        $this->postJson("/api/auth/staff/{$duplicate->id}/invite")->assertStatus(422);
    }
}
