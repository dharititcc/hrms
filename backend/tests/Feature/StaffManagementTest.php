<?php

namespace Tests\Feature;

use App\Models\Staff;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class StaffManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_update_and_delete_owned_staff(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $create = $this->postJson('/api/auth/staff', [
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+1 555 0100',
            'role' => 'manager',
            'status' => 'active',
        ]);

        $create->assertCreated()->assertJsonPath('data.email', 'ada@example.com');
        $staff = Staff::query()->where('owner_id', $user->id)->firstOrFail();

        $this->putJson("/api/auth/staff/{$staff->id}", [
            'name' => 'Ada Byron Lovelace',
            'email' => 'ada@example.com',
            'phone' => null,
            'role' => 'admin',
            'status' => 'inactive',
        ])->assertOk()->assertJsonPath('data.status', 'inactive');

        $this->deleteJson("/api/auth/staff/{$staff->id}")->assertOk();
        $this->assertDatabaseMissing('staff', ['id' => $staff->id]);
    }

    public function test_user_cannot_view_another_users_staff(): void
    {
        $owner = User::factory()->create();
        $otherUser = User::factory()->create();
        $staff = Staff::create([
            'owner_id' => $owner->id,
            'name' => 'Private teammate',
            'email' => 'private@example.com',
            'role' => 'member',
            'status' => 'active',
        ]);

        Sanctum::actingAs($otherUser);

        $this->getJson('/api/auth/staff')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/staff/{$staff->id}")->assertForbidden();
    }
}
