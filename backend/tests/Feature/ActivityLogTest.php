<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_creating_and_updating_a_record_writes_the_timeline(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/projects', ['name' => 'Website relaunch', 'status' => 'planning'])->assertCreated();
        $project = Project::firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'owner_id' => $user->id,
            'user_id' => $user->id,
            'action' => 'created',
            'entity' => 'project',
            'entity_id' => $project->id,
        ]);

        $this->putJson("/api/auth/projects/{$project->id}", ['name' => 'Website relaunch', 'status' => 'active'])->assertOk();

        $update = AuditLog::where('action', 'updated')->firstOrFail();
        $this->assertSame('project', $update->entity);
        $this->assertContains('status', $update->metadata['changed']);
        // Enums must be stored as scalars, not serialized objects.
        $this->assertSame('planning', $update->metadata['old']['status']);
        $this->assertSame('active', $update->metadata['new']['status']);
    }

    public function test_timeline_can_be_fetched_for_a_record(): void
    {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/auth/projects', ['name' => 'Alpha', 'status' => 'planning'])->assertCreated();
        $project = Project::firstOrFail();

        $this->getJson("/api/auth/activity?entity=project&entity_id={$project->id}")
            ->assertOk()
            ->assertJsonPath('data.0.action', 'created')
            ->assertJsonPath('data.0.user_name', $user->name);
    }

    public function test_activity_is_scoped_to_the_workspace(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();

        Sanctum::actingAs($owner);
        $this->postJson('/api/auth/projects', ['name' => 'Confidential', 'status' => 'planning'])->assertCreated();
        $project = Project::firstOrFail();

        Sanctum::actingAs($intruder);
        $this->getJson('/api/auth/activity')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/auth/activity?entity=project&entity_id={$project->id}")->assertNotFound();
    }
}
